# Optimization tests

These tests protect optimizations that acceptance tests cannot fully verify.
Removing a cache or adding unnecessary autoloading can leave decoded results
unchanged while making decoding slower. Direct checks make those regressions
visible to mutation testing without relying on timing thresholds.

- `MetadataCacheTest` checks that class decoders are compiled once per class, that
  root decodes reuse them and the nodes of enum roots, that
  failed and incomplete declarations are compiled again, and that field declarations
  are compiled again after a missing class loads. It also checks that equivalent
  `JsonType` descriptors share one compiled node, and that a descriptor is compiled
  again after its missing item class loads.
- `ConstructorHydrationPlanTest` checks that cached class decoders still validate each
  input, that values of a field's declared scalar type skip the field's node, and
  that unions keep the JSON value types they decode.
- `ConstructorValidationPlanTest` checks that cached decoders still validate and convert each
  input, preserve error paths, report the first invalid field in declaration order,
  and recheck unresolved dependencies.
- `AutoloadingTest` checks that built-in type names, and the classes of irrelevant
  or absent values, do not reach the autoloader. It does not check which of the
  library's own classes load; the cold benchmarks measure that cost.

Keep assertions in ordinary PHPUnit test methods. Use data providers for inputs
and expected results. Prefer acceptance coverage for externally visible behavior;
use benchmarks to measure execution time.
