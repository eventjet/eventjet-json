# Test redundancy audit

Audited `tabula-rasa` at `2b4715878ba126d513400af0b90111fa3cc29a93` on October 7, 2026.

## Findings

The baseline has 17,965 tests: 17,961 acceptance datasets and four cache unit tests.
Serializing each acceptance dataset together with its test method identified 86
groups containing 96 excess identical datasets. Nine datasets containing closures
could not be fingerprinted this way. These counts are a conservative inventory,
not a claim that all other datasets provide distinct protection.

The largest overlap comes from composition of providers. The collection-error
matrix has 1,280 cases. Each runs directly, after one valid object, and after three
valid objects. Root maps repeat both warmed-array variants, yielding 5,120 warm
cases across array and map roots.

Other substantial overlaps remain: root arrays batch the object round-trip cases,
root maps replay those batches, and older scalar/list/map error providers overlap
with the general collection matrix. Different declarations, hydration paths,
shapes, and error paths can still catch different bugs; matching coverage alone
does not establish that these cases are dispensable.

## Applied reductions

| Change | Fewer test cases | Reason |
| --- | ---: | --- |
| Keep three valid objects before each warm-cache error | 2,560 | Retains repeated cache reuse and a nonzero error index for both root kinds. |
| Generate each nested-tuple declaration once | 18 | Both construction orders produce the same nested tuple when both containers are tuples. |
| Do not repeat a singleton as a combined collection | 2 | The single-value case already supplies the same collection. |
| Remove list-parser depths 0, 1, and 2 from the boundary provider | 3 | The syntax provider already supplies identical inputs and expected trees. Depth-limit boundaries remain. |
| **Total** | **2,583 (14.4%)** | |

The candidate has 15,382 tests and 56,695 assertions, compared with 64,458 baseline
assertions. Both suites pass. The warm-cache reduction was tested separately
before testing the combined candidate in an isolated copy.

Both suites cover exactly the same 1,388 production statement lines out of 1,392.
The comparison checks file and line identities, not just the percentage. PCOV does
not provide branch coverage, so this result is not a branch-equivalence claim.

PHPStan, Psalm, Mago lint, Mago analysis, and the formatting check pass for the
candidate. Production code, test methods, and quality thresholds are unchanged.

## Mutation comparison and runner correction

The initial comparison used Infection's optional `--only-covering-test-cases`
selector. Both suites generated the same 1,323 mutations and retained every
baseline test kill. The selector reported four survivors and 99.7% MSI in both
runs. Those were false survivor reports caused by test selection, not missing
regression cases. The earlier conclusion that the repository's normal mutation
check failed was incorrect.

A focused rerun **without** that selector killed all seven mutations generated
by `FalseValue`, `TrueValue`, and `UnwrapLtrim` in the three affected files,
including all four reported survivors. Each was caught by an existing assertion:

| Mutation | Existing case that catches it |
| --- | --- |
| `ArrayJsonType.php:28`, list becomes nonempty | Empty enum list at depth 1 |
| `MapJsonType.php:31`, ArrayObject flag becomes false | Empty root map |
| `MapJsonType.php:66`, nested map becomes nullable | Nested list of maps rejects an object, with an exact error message |
| `PhpDocClassNameResolver.php:26`, remove leading-backslash trimming | Global namespace-relative item-type mismatch |

The suite constructs type descriptors in data providers and reuses metadata
across tests. A test can observe a bad descriptor or cached value without being
recorded as executing the line that created it. The observed result is that
coverage-selected tests miss failures which the normal runner detects. Do not
use that selector to approve future reductions in this suite.

No additional regression cases or suppressions were needed to catch these
mutations. The normal full mutation check is the final acceptance criterion.
Composer's default 300-second process limit is too short for this run, so the
mutation command uses `COMPOSER_PROCESS_TIMEOUT=0`. Infection's own 30-second
per-mutation timeout and its quality thresholds remain unchanged.

The final normal run completed successfully with **100% MSI, 100% mutation code
coverage, and zero survivors** across 1,323 mutations: 1,309 test kills, ten
static-analysis kills, two errors in mutated code, and two timeouts. The dependency,
formatting, Psalm, PHPStan, Mago, and PHPUnit checks also pass. `composer check`
completed those checks before its wrapper timed out during Infection; the normal
`composer infection` command then completed separately with the wrapper limit
lifted. The raw final output is `final-infection-console.txt` in the audit directory.

## Method and reproducibility

All PHP commands run in the project's PHP Docker Compose service, using PHP
8.4.26, PHPUnit 12.5.38, Infection 0.35.6, and PCOV 1.0.12. The audit uses a copied
dependency installation and lockfile for both runs. The repository does not track
the Composer lockfile.

1. Reflect the acceptance test's `DataProviderExternal` attributes and enumerate
   each provider. Group `sha256(test method + serialize(arguments))` fingerprints,
   excluding serialization failures. Preserve raw JSON, target class, and expected
   result; do not normalize away shape or declaration differences.
2. Run PHPUnit with `--log-junit` and `--coverage-clover` on the baseline, the
   warm-cache-only candidate, and the combined candidate. Compare the sets of
   covered production statement lines.
3. Run Infection on the unchanged source with the same mutators and exclusions.
   The initial audit used `--threads=12 --with-uncovered --only-covering-test-cases
   --no-progress`; the correction above explains why that optional selector is
   unsuitable here. Validate the final state with the normal `composer check`
   command, which runs Infection without it. Set `COMPOSER_PROCESS_TIMEOUT=0`
   and use a 3 GB PHP memory limit for these audit processes.
4. Compare individual mutations by relative source path, source line, mutator,
   original code, and replacement code. Keep test kills separate from static
   analysis kills, errors, uncovered mutations, and timeouts.

Raw inventories, JUnit, Clover, mutation reports, and runner output are retained
in `/tmp/eventjet-test-audit`. These are local audit artifacts, not permanent
repository dependencies. Concurrent mutation runs make wall-clock timings
unsuitable for a performance comparison.

## Remaining candidates

The reduced inventory still contains 73 excess identical datasets. These cluster
around malformed parser declarations, collection-name variants that do not affect
some container shapes, and nested-shape boundary examples. Consolidate their
generators and ownership only with a separate comparison; do not add a global
runtime filter that hides duplicate generation.

The 250 three-level shape combinations, 36 trailing-JSON cases, older focused error
providers, and object-to-root collection expansions remain in place. This audit
does not establish that removing those groups preserves their regression value.
