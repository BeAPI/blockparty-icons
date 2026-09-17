#!/usr/bin/env bash
#
# Stand up the Blockparty Icons perf environment.
#
#   tests/perf/bin/setup.sh [--scale=full|small] [--skip-fixtures]
#
# Produces an isolated WordPress instance on http://localhost:8899 with:
#   - 200 SVG icons in a theme folder    -> collection `perf-theme`
#   - 300 SVG icons in the media library -> collection `perf-media`
#   - icon sizes spread from ~10 KB to ~2 MB, several above memcached's 1 MB limit
#   - a persistent object cache that refuses items over 1 MB, exactly like VIP
#
set -euo pipefail

# shellcheck source=tests/perf/bin/_env.sh
source "$( dirname "${BASH_SOURCE[0]}" )/_env.sh"

SCALE="full"
SKIP_FIXTURES=0

for arg in "$@"; do
	case "$arg" in
		--scale=*)       SCALE="${arg#*=}" ;;
		--skip-fixtures) SKIP_FIXTURES=1 ;;
		*) echo "unknown argument: $arg" >&2; exit 1 ;;
	esac
done

cd "$PERF_ROOT"

# ---------------------------------------------------------------- fixtures

if [ "$SKIP_FIXTURES" -eq 0 ]; then
	say "Generating SVG fixtures (scale=${SCALE})"
	node tests/perf/bin/generate-fixtures.mjs --scale="$SCALE"
else
	say "Reusing existing fixtures"
fi

if [ ! -f tests/perf/fixtures/manifest.json ]; then
	echo "No fixtures found. Run without --skip-fixtures." >&2
	exit 1
fi

# ------------------------------------------------------------------- build

# The plugin's classes are found through Composer's PSR-4 autoloader. Without
# vendor/ present, blockparty-icons.php loads but every one of its classes is missing.
if [ ! -f vendor/autoload.php ]; then
	say "Installing Composer dependencies (the plugin autoloads via PSR-4)"
	composer install --no-interaction --no-progress
fi

if [ ! -d build ]; then
	say "Building plugin assets"
	npm run build
fi

# --------------------------------------------------------------- wp-env up

say "Starting wp-env (port ${WP_ENV_PORT})"
perf_wp_env start --update

perf_resolve_containers
echo "  containers: ${PERF_PROJECT}-*"

# wp-env maps directories reliably; single-file mappings are less consistent
# across versions. Copy the drop-in in if the mapping did not take.
say "Verifying object cache drop-in"
if incli test -f wp-content/object-cache.php; then
	echo "  object-cache.php present"
else
	docker cp tests/perf/drop-ins/object-cache.php \
		"${PERF_WP_CONTAINER}:/var/www/html/wp-content/object-cache.php"
	docker cp tests/perf/drop-ins/object-cache.php \
		"${PERF_CLI_CONTAINER}:/var/www/html/wp-content/object-cache.php"
	echo "  object-cache.php copied into the containers"
fi

for required in wp-content/mu-plugins/bpi-perf-probe.php wp-content/bpi-fixtures/manifest.json; do
	if ! incli test -f "$required"; then
		echo "Missing ${required} in the container — wp-env mappings did not apply." >&2
		exit 1
	fi
done
echo "  mu-plugins and fixtures mounted"

# ------------------------------------------------------------- wp settings

say "Configuring WordPress"
wpcli theme activate blockparty-perf
wpcli plugin activate blockparty-icons
wpcli rewrite structure '/%postname%/' --hard
wpcli option update blog_public 0

# --------------------------------------------------------- media library

WANT_MEDIA="$( node -e "const m=require('./tests/perf/fixtures/manifest.json');process.stdout.write(String(m.sets.media.count))" )"
MEDIA_COUNT="$( wpcli post list --post_type=attachment --post_mime_type=image/svg+xml --format=count | tr -d '\r' )"

if [ "$MEDIA_COUNT" -lt "$WANT_MEDIA" ]; then
	say "Importing ${WANT_MEDIA} SVG icons into the media library (slowest step, a few minutes)"
	incli bash -c 'wp media import /var/www/html/wp-content/bpi-fixtures/media-icons/*.svg --porcelain > /dev/null'
	MEDIA_COUNT="$( wpcli post list --post_type=attachment --post_mime_type=image/svg+xml --format=count | tr -d '\r' )"
else
	say "Media library already holds ${MEDIA_COUNT} SVG attachments, skipping import"
fi

# --------------------------------------------------------------- content

say "Creating benchmark pages"

make_page() {
	local slug="$1" title="$2" content="$3"
	local existing
	existing="$( wpcli post list --post_type=page --name="$slug" --format=count | tr -d '\r' )"
	if [ "$existing" != "0" ]; then
		echo "  ${slug} already exists"
		return
	fi
	wpcli post create \
		--post_type=page \
		--post_status=publish \
		--post_name="$slug" \
		--post_title="$title" \
		--post_content="$content" \
		--porcelain > /dev/null
	echo "  ${slug} created"
}

ONE_ICON='<!-- wp:blockparty/icon {"icon":{"label":"theme-icon-001","name":"theme-icon-001","type":"raw","collection":"perf-theme"},"size":48} /-->'

MANY_ICONS="$( node -e '
const m = require("./tests/perf/fixtures/manifest.json");
process.stdout.write(
  m.sets.theme.entries.slice(0, 20).map((e) =>
    `<!-- wp:blockparty/icon {"icon":{"label":"${e.name}","name":"${e.name}","type":"raw","collection":"perf-theme"},"size":32} /-->`
  ).join("\n")
);
' )"

make_page "perf-empty"  "Perf - no icon block"  '<!-- wp:paragraph --><p>No icon on this page.</p><!-- /wp:paragraph -->'
make_page "perf-single" "Perf - one icon block" "$ONE_ICON"
make_page "perf-many"   "Perf - twenty icons"   "$MANY_ICONS"

# ---------------------------------------------------------------- summary

say "Verifying registered collections"
wpcli cache flush > /dev/null 2>&1 || true
wpcli eval '
$c = \Blockparty\Icons\get_icon_collections();
$total = 0;
foreach ( $c as $name => $col ) {
	printf( "  %-12s %4d icons\n", $name, $col->count() );
	$total += $col->count();
}
printf( "  %-12s %4d icons\n", "TOTAL", $total );
'

cat <<EOF

Environment ready.

  site   ${PERF_BASE}
  admin  ${PERF_BASE}/wp-admin  (admin / password)
  pages  /perf-empty/  /perf-single/  /perf-many/
  media  ${MEDIA_COUNT} SVG attachments

Next:
  tests/perf/bin/bench.sh --label=baseline
EOF
