#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../web"

export SIMPLETEST_DB="${SIMPLETEST_DB:-mysql://db:db@db/db}"
export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-http://127.0.0.1}"

mkdir -p sites/simpletest/browser_output

../vendor/bin/phpunit -c core modules/custom/ticketdesk/tests "$@"
