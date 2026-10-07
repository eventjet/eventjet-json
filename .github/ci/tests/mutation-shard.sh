#!/usr/bin/env bash
set -euo pipefail

runner="$(cd "$(dirname "$0")/.." && pwd)/mutation-shard.php"
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
cd "$fixture"

expect_failure() {
    if php "$runner" "$@" > output 2> error; then
        echo "Expected failure for arguments: $*"
        exit 1
    fi
    test ! -s output
    test -s error
}

expect_failure
expect_failure 0 2
expect_failure 3 2
expect_failure 1 0
expect_failure 1 -1
expect_failure invalid 2
expect_failure 1 2 extra
expect_failure 1 2 # Missing source directory.
mkdir -p src/nested
expect_failure 1 2 # Empty source directory.

# Deliberately create equal-weight files in reverse lexical order.
printf 'one\ntwo\nthree\nfour\n' > src/z.php
printf '  // ignored\n\n  * ignored\r\none\r\ntwo\r\nthree\r\nfour\r\n' > src/a.php
printf 'one\ntwo\nthree\n' > src/b.php
printf 'one\ntwo\n' > src/nested/c.php
printf ' // ignored\n\t\n' > src/empty.php
printf 'not a PHP source\n' > src/ignored.txt

printf 'src/a.php\nsrc/b.php\n' > expected-1
printf 'src/z.php\nsrc/nested/c.php\nsrc/empty.php\n' > expected-2
for shard in 1 2; do
    php "$runner" "$shard" 2 > actual
    cmp "expected-$shard" actual
done

# More shards than files must leave empty groups and still cover every file once.
: > all
for shard in 1 2 3 4 5 6; do
    php "$runner" "$shard" 6 >> all
done
printf 'src/a.php\nsrc/b.php\nsrc/empty.php\nsrc/nested/c.php\nsrc/z.php\n' > expected-all
LC_ALL=C sort all > sorted-all
cmp expected-all sorted-all
php "$runner" 6 6 > empty
test ! -s empty

echo 'Mutation partition weights, tie-breaking, coverage, empty groups, and invalid inputs passed.'
