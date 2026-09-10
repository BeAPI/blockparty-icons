#!/usr/bin/env bash
#
# Run the PHPUnit suite inside the wp-env tests container.
#
#   tests/bin/phpunit.sh [phpunit arguments...]
#
# The suite needs a real WordPress install, which wp-env's `tests-wordpress`
# service provides along with the WordPress PHPUnit library at /wordpress-phpunit.
#
# wp-env's own `run` subcommand cannot be pointed at a specific environment, so we
# resolve the container from the port it publishes and talk to it with docker exec.
#
set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../.." && pwd )"
PORT="${WP_ENV_TESTS_PORT:-8891}"

cd "$ROOT"

CONTAINER="$( docker ps \
	--filter "publish=${PORT}" \
	--filter "name=-tests-wordpress-1" \
	--format '{{.Names}}' | head -n1 )"

if [ -z "$CONTAINER" ]; then
	cat >&2 <<EOF
No wp-env test container is publishing port ${PORT}.

Start one with:
  WP_ENV_PORT=8890 WP_ENV_TESTS_PORT=${PORT} npx wp-env start

Override the port with WP_ENV_TESTS_PORT if you run wp-env elsewhere.
EOF
	exit 1
fi

CLI="${CONTAINER%-tests-wordpress-1}-tests-cli-1"

exec docker exec -u 33 \
	-w /var/www/html/wp-content/plugins/blockparty-icons \
	"$CLI" vendor/bin/phpunit "$@"
