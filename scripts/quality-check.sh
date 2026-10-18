#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
echo '[1/4] PHP syntax checks'
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find src public scripts tests -name '*.php' -print0)
echo 'PASS: all PHP source files parse'
echo '[2/4] PHP verification tests'
php tests/run.php
echo '[3/4] SQLite SQL smoke check (Python, not PDO integration)'
python3 tests/check_schema_sql.py
echo '[4/4] Exact-source learning documentation check'
python3 scripts/validate-walkthrough.py
