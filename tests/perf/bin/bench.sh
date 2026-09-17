#!/usr/bin/env bash
#
# Run the Blockparty Icons perf scenarios and write a comparable result set.
#
#   tests/perf/bin/bench.sh --label=baseline [--runs=5] [--latency-us=0]
#
# --label      names the result file, so before/after runs can be diffed
# --runs       samples per scenario (the first is discarded as a warm-up)
# --latency-us artificial per-file latency for media-library reads, emulating VIP Files
#
set -euo pipefail

# shellcheck source=tests/perf/bin/_env.sh
source "$( dirname "${BASH_SOURCE[0]}" )/_env.sh"

BASE="$PERF_BASE"
TOKEN="$PERF_TOKEN"

LABEL=""
RUNS=5
LATENCY=0

for arg in "$@"; do
	case "$arg" in
		--label=*)      LABEL="${arg#*=}" ;;
		--runs=*)       RUNS="${arg#*=}" ;;
		--latency-us=*) LATENCY="${arg#*=}" ;;
		*) echo "unknown argument: $arg" >&2; exit 1 ;;
	esac
done

if [ -z "$LABEL" ]; then
	echo "--label is required (e.g. --label=baseline)" >&2
	exit 1
fi

cd "$PERF_ROOT"
mkdir -p tests/perf/results

perf_resolve_containers

LOG_HOST="tests/perf/results/${LABEL}.log"
SIZES_FILE="tests/perf/results/${LABEL}.sizes"
: > "$SIZES_FILE"

# wp-admin is gated by auth_redirect(), which validates the *auth*-scheme cookie,
# while REST and the rest of WordPress read the logged_in one. Mint both.
say "Minting an admin session for the editor scenario"
ADMIN_ID="$( wpcli user list --role=administrator --field=ID --number=1 | tr -d '\r' )"
LOGIN_COOKIE_NAME="$( wpcli eval 'echo LOGGED_IN_COOKIE;' | tr -d '\r' )"
AUTH_COOKIE_NAME="$( wpcli eval 'echo AUTH_COOKIE;' | tr -d '\r' )"
LOGIN_COOKIE_VALUE="$( wpcli eval "echo wp_generate_auth_cookie( ${ADMIN_ID}, time() + 7200, 'logged_in' );" | tr -d '\r' )"
AUTH_COOKIE_VALUE="$( wpcli eval "echo wp_generate_auth_cookie( ${ADMIN_ID}, time() + 7200, 'auth' );" | tr -d '\r' )"

if [ -z "$LOGIN_COOKIE_VALUE" ] || [ -z "$AUTH_COOKIE_VALUE" ]; then
	echo "Could not mint auth cookies; the editor scenario will redirect." >&2
else
	echo "  admin user ${ADMIN_ID}, cookies minted"
fi

say "Resetting perf log"
incli rm -f /var/www/html/wp-content/bpi-perf.log >/dev/null 2>&1 || true

# hit URL, scenario-name, cold|warm, [auth]
hit() {
	local url="$1" name="$2" cache="$3" auth="${4:-noauth}"
	local sep="?"
	case "$url" in *\?*) sep="&" ;; esac

	local full="${BASE}${url}${sep}bpi_perf_label=${name}&bpi_perf_latency_us=${LATENCY}"
	local -a curl_args=( -s -o /dev/null -w '%{http_code} %{size_download}' --max-time 600 )

	if [ "$auth" = "auth" ]; then
		curl_args+=( -H "X-BPI-Perf-Token: ${TOKEN}" )
		if [ -n "$LOGIN_COOKIE_VALUE" ]; then
			curl_args+=( -b "${LOGIN_COOKIE_NAME}=${LOGIN_COOKIE_VALUE}" )
			curl_args+=( -b "${AUTH_COOKIE_NAME}=${AUTH_COOKIE_VALUE}" )
		fi
	fi

	local last_bytes=0
	for i in $( seq 1 "$RUNS" ); do
		if [ "$cache" = "cold" ]; then
			wpcli cache flush >/dev/null 2>&1 || true
		fi
		local out code bytes
		out="$( curl "${curl_args[@]}" "$full" )"
		code="${out%% *}"
		bytes="${out##* }"
		last_bytes="$bytes"
		if [ "$code" != "200" ]; then
			printf '    %s run %s -> HTTP %s\n' "$name" "$i" "$code" >&2
		fi
	done

	printf '%s\t%s\n' "$name" "$last_bytes" >> "$SIZES_FILE"
	printf '  %-28s %s cache, %s runs, %s KB response\n' \
		"$name" "$cache" "$RUNS" "$(( last_bytes / 1024 ))"
}

say "Running scenarios (latency=${LATENCY}us per media read)"

# --- front end -------------------------------------------------------------
hit "/perf-empty/"  "front-empty-warm"  warm
hit "/perf-empty/"  "front-empty-cold"  cold
hit "/perf-single/" "front-single-warm" warm
hit "/perf-single/" "front-single-cold" cold
hit "/perf-many/"   "front-many-warm"   warm

# --- REST (editor data path) ----------------------------------------------
hit "/wp-json/icons/v1/collections?context=edit"                 "rest-collections"   warm auth
hit "/wp-json/icons/v1/perf-theme?context=edit&per_page=50"      "rest-theme-p1"      warm auth
hit "/wp-json/icons/v1/perf-media?context=edit&per_page=50"      "rest-media-p1"      warm auth
hit "/wp-json/icons/v1/perf-media?context=edit&per_page=50&page=4" "rest-media-p4"    warm auth
hit "/wp-json/icons/v1/perf-theme?context=edit&search=icon-1"    "rest-theme-search"  warm auth

# --- editor ----------------------------------------------------------------
hit "/wp-admin/post-new.php?post_type=page" "editor-new-page" warm auth

say "Collecting results"
incli cat /var/www/html/wp-content/bpi-perf.log > "$LOG_HOST" 2>/dev/null || true

if [ ! -s "$LOG_HOST" ]; then
	echo "Perf log is empty — is the probe mu-plugin loaded?" >&2
	exit 1
fi

node tests/perf/bin/report.mjs --label="$LABEL"
