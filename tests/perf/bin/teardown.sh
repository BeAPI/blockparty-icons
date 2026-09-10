#!/usr/bin/env bash
#
# Destroy the perf environment. Fixtures and captured results are kept.
#
#   tests/perf/bin/teardown.sh [--purge-fixtures]
#
set -euo pipefail

# shellcheck source=tests/perf/bin/_env.sh
source "$( dirname "${BASH_SOURCE[0]}" )/_env.sh"

say "Destroying the perf environment"
perf_wp_env destroy || true

if [ "${1:-}" = "--purge-fixtures" ]; then
	say "Removing generated fixtures"
	rm -rf "${PERF_ROOT}/tests/perf/fixtures"
fi

echo "Done."
