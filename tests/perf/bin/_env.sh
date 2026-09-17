#!/usr/bin/env bash
#
# Shared settings for the perf harness. Sourced by setup.sh and bench.sh.
#
# Three wp-env facts shape this file (@wordpress/env 10.39):
#
# 1. There is no --config flag. wp-env always reads .wp-env.json from the current
#    working directory. The perf environment therefore lives in its own directory,
#    tests/perf/env/, with its own .wp-env.json. That also gives it its own project
#    hash, so its containers and database are fully isolated from the dev env.
#
# 2. Ports come from WP_ENV_PORT / WP_ENV_TESTS_PORT, not from the config file.
#    We pin them so the perf instance can run alongside the dev env on 8888/8889.
#
# 3. `wp-env run` takes no config either, so we address the containers directly
#    with docker exec, resolving them by the port they publish.

export WP_ENV_PORT="${WP_ENV_PORT:-8899}"
export WP_ENV_TESTS_PORT="${WP_ENV_TESTS_PORT:-8898}"

PERF_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../../.." && pwd )"
PERF_ENV_DIR="${PERF_ROOT}/tests/perf/env"
PERF_BASE="http://localhost:${WP_ENV_PORT}"
PERF_TOKEN="local-perf-harness-only"

export PERF_ROOT PERF_ENV_DIR PERF_BASE PERF_TOKEN

say() {
	printf '\n\033[1m==> %s\033[0m\n' "$1"
}

# Run a wp-env subcommand against the perf environment.
perf_wp_env() {
	( cd "$PERF_ENV_DIR" && npx --prefix "$PERF_ROOT" wp-env "$@" )
}

# Resolve the perf environment's container names from the published port.
perf_resolve_containers() {
	local wp
	wp="$( docker ps \
		--filter "publish=${WP_ENV_PORT}" \
		--filter "name=-wordpress-1" \
		--format '{{.Names}}' | head -n1 )"

	if [ -z "$wp" ]; then
		echo "No running wp-env WordPress container publishing port ${WP_ENV_PORT}." >&2
		echo "Start it with: tests/perf/bin/setup.sh" >&2
		return 1
	fi

	PERF_WP_CONTAINER="$wp"
	PERF_PROJECT="${wp%-wordpress-1}"
	PERF_CLI_CONTAINER="${PERF_PROJECT}-cli-1"

	export PERF_WP_CONTAINER PERF_PROJECT PERF_CLI_CONTAINER
}

# Run a command inside the CLI container, as the web user, at the WordPress root.
incli() {
	docker exec -u 33 -w /var/www/html "$PERF_CLI_CONTAINER" "$@"
}

# Run WP-CLI inside the perf environment.
wpcli() {
	incli wp "$@"
}
