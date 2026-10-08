# Optimization tests

These tests protect optimizations that acceptance tests cannot fully verify.
Removing a cache or adding unnecessary autoloading can leave decoded results
unchanged while making decoding slower. Direct checks make those regressions
visible to mutation testing without relying on timing thresholds.

- `MetadataCacheTest` checks constructor-plan reuse, cache independence, and retries after
  unresolved dependencies or loader failures.
- `ConstructorValidationPlanTest` checks that cached plans still validate and convert each
  input, preserve error paths and validation-before-conversion order, and recheck
  unresolved dependencies.
- `AutoloadingTest` checks that built-in types and irrelevant or absent values do
  not trigger unnecessary class loading.

Keep assertions in ordinary PHPUnit test methods. Use data providers for inputs
and expected results. Prefer acceptance coverage for externally visible behavior;
use benchmarks to measure execution time.
