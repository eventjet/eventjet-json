# Direct decoding integration

`Json::decode()` now uses the selected PHP-only direct parser. The reference is
`tabula-rasa` at `512ca4e`, including constructor-plan reuse and homogeneous-list
conversion. The previous implementation remains `Internal\NativeJsonDecoder`,
both as a compatibility fallback and as the independent benchmark reference.
The public signature and `validateType()` behavior remain unchanged.

## Implementation

The production path retains fused scalar-object matching, ignored-subtree
projection, compact and flexible generated graph parsers, and the general
validated cursor parser with 8 KiB scalar-record windows. It omits the experimental
switches, alternative window sizes, container indexing and lazy breadcrumb modes.
Scalar plans are named immutable records rather than numbered tuples.

This is a hybrid parser: PCRE performs native scanning and whole-schema validation,
while generated PHP walks source cursors and constructs target objects. Strings
and scalar collection leaves can still use `json_decode()`. The conventional
PHP lexer/recursive parser remains the experimental `pure` mode; it was slower
in the [first-pass comparisons](RESULTS.md). No FFI or new runtime dependency is
required. Generated code uses reflection metadata and fixed emitter strings;
input JSON is never interpolated into PHP source.

Full syntax validation precedes constructors. Native fallback preserves error
ordering, unsupported shapes, leading-NUL property handling and application-defined
root `JsonType::decodeValue()` behavior. PCRE resource failures fall back without
changing the configured limits after decoding. Schema caches are process-local;
their allocation is excluded from warmed measurements, not from cold measurements.

The parser and compiler are substantial internal modules. They have local lint
exceptions for generated code, scanner control flow and complexity. Project-wide
rules and mutation thresholds are unchanged. These exceptions are a maintenance
cost of the optimized implementation, not evidence that it is simple.

## Measurements

Windows, PHP 8.5.11, CLI OPcache enabled, PHP JIT disabled, PCRE JIT enabled.
Fifteen randomized paired rounds measure warmed elapsed time; three fresh-process
samples per mode measure cold cost and PHP allocator peaks. Timings are a CPU-cost
proxy, not a CPU counter. These are local observations, not deployment guarantees.
Workload creation and input storage are excluded; the entire returned graph is
included. Native and production output equality is checked outside timing.

| Workload | Paired direct/native time | Native warm peak | Direct warm peak |
| --- | ---: | ---: | ---: |
| Scalar object | 0.678 | 1,064 B | 616 B |
| 1,000 records | 0.401 | 812,128 B | 215,200 B |
| 10,000 records | 0.360 | 8,239,648 B | 2,212,960 B |
| 10,000 root records | 0.381 | 8,239,192 B | 2,213,368 B |
| Deep chain 384 | 0.208 | 1,386,224 B | 31,296 B |
| GitHub webhook | 0.849 | 51,536 B | 12,656 B |
| Stripe invoice | 1.292 | 70,816 B | 36,736 B |
| Kubernetes deployment | 0.646 | 57,848 B | 22,240 B |
| JSON:API document | 1.080 | 25,192 B | 12,192 B |
| AWS MSK event | 0.827 | 8,528 B | 3,624 B |
| Long scalar lists | 0.994 | 143,648 B | 130,752 B |
| Ignored record tree | 0.113 | 37,853,536 B | 2,080 B |
| Ignored 4 MiB string | 1.135 | 6,292,576 B | 2,040 B |

Below 1 is faster. Large record batches improve time and allocation substantially.
Stripe, JSON:API and the ignored string are slower here; long scalar lists are
essentially tied. The older baseline's JSON:API gain does not survive the newer
native hydration optimizations. This is deliberately not a universal speed claim.

First-call costs can outweigh warmed gains, particularly in short-lived requests:

| Workload | Native cold | Direct cold | Native cold peak | Direct cold peak |
| --- | ---: | ---: | ---: | ---: |
| Scalar object | 0.86 ms | 1.93 ms | 4,896 B | 77,912 B |
| 1,000 records | 7.66 ms | 8.56 ms | 826,464 B | 263,176 B |
| GitHub webhook | 6.43 ms | 11.08 ms | 173,056 B | 670,632 B |
| Stripe invoice | 22.81 ms | 22.44 ms | 344,144 B | 1,270,952 B |
| Kubernetes deployment | 10.43 ms | 15.48 ms | 190,528 B | 727,288 B |
| Ignored string | 5.94 ms | 12.93 ms | 6,296,408 B | 6,291,512 B |

Raw [paired samples](results/production/paired-windows-php85.json) and
[isolated samples](results/production/isolated-windows-php85.json) include all
workloads and dispersion. Earlier reports describe different runtimes, OPcache
settings and baselines; do not compare their absolute times directly.

```sh
php -d opcache.enable_cli=1 experiments/direct-json/paired.php 15 native,production "scalar object,record batch 1000,record batch 10000,root records 10000,deep chain 384,GitHub pull request webhook,Stripe invoice,Kubernetes deployment,JSON:API compound document,AWS Lambda Amazon MSK event,long scalar lists,ignored tree,ignored string"
php -d opcache.enable_cli=1 experiments/direct-json/bench.php 3 native,production "scalar object,record batch 1000,record batch 10000,root records 10000,deep chain 384,GitHub pull request webhook,Stripe invoice,Kubernetes deployment,JSON:API compound document,AWS Lambda Amazon MSK event,long scalar lists,ignored tree,ignored string"
```

## Verification

- PHPUnit: 563 tests, 1,527 assertions on PHP 8.5.11/Windows and PHP 8.4.26/Linux
  (the latter with PCOV coverage and strict coverage metadata).
- Differential verification: 92,909 comparisons with zero failures on PHP 8.4.1,
  PHP 8.5.11, and PHP 8.5.11 with PCRE JIT disabled. This includes cold compilation
  at very low PCRE limits, custom root descriptors, syntax/error precedence,
  numeric/Unicode/depth boundaries and constructor side effects.
- PHPStan, Psalm, Mago analysis/lint/format and dependency checking pass.
- Mutation results are recorded below when the full run completes.
