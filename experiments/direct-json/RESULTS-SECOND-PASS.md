# Second pass: whole-graph compilation

The first pass stopped too early. PHP-only direct construction can outperform
the production `json_decode()` plus hydration path on more than scalar record
batches. Compiling a whole type graph, validating it in one grammar and generating
cursor-based constructors closes much of the gap on real documents. The combined
`extreme` mode still loses on some inputs and incurs schema-compilation cost;
these results do not justify silently replacing the production decoder.

No FFI, native extension, external executable or new runtime dependency is used.
PCRE and JSON are the existing PHP facilities. Scalar collection leaves and escaped
strings may still be decoded natively; the avoided work is constructing a generic
object graph and subsequently copying it into typed objects.

## Final measurements

Windows, PHP 8.5.11, CLI OPcache/JIT disabled, PCRE JIT enabled. The isolated
profile uses five fresh-process samples per mode; paired timing uses 21 randomized
rounds. Ratios below are `extreme / native`: below 1 is faster. Peak allocation is
from the isolated workers and includes the complete returned graph.

| Workload | Isolated time ratio | Paired time ratio | Native peak | Direct peak |
| --- | ---: | ---: | ---: | ---: |
| Scalar object | 0.66 | 0.66 | 1,064 B | 616 B |
| Public scalar properties | 0.48 | 0.51 | 2,928 B | 720 B |
| Named enums | 0.49 | 0.51 | 1,072 B | 512 B |
| Long scalar lists | 0.90 | 0.93 | 143,648 B | 130,784 B |
| Long string map | 0.94 | 0.91 | 133,960 B | 138,040 B |
| 1,000 records | 0.30 | 0.26 | 812,128 B | 215,232 B |
| 10,000 records | 0.28 | 0.29 | 8,239,648 B | 2,212,992 B |
| 10,000 root records | 0.30 | 0.33 | 8,239,192 B | 2,213,368 B |
| Recursive collections | 1.39 | 1.36 | 65,120 B | 32,016 B |
| JSON:API compound document | 0.96 | 0.92 | 25,192 B | 12,208 B |
| AWS Lambda Amazon MSK event | 0.76 | 0.77 | 8,528 B | 3,640 B |
| Stripe invoice | 1.24 | 1.27 | 70,816 B | 36,752 B |
| GitHub pull request webhook | 0.81 | 0.83 | 51,536 B | 12,688 B |
| Kubernetes deployment | 0.56 | 0.55 | 57,848 B | 22,272 B |
| Ignored 50,000-record tree | 0.12 | 0.16 | 37,853,536 B | 2,080 B |
| Ignored 4 MiB string | 1.45 | 0.53 | 6,292,576 B | 2,040 B |
| Deep chain 384 | 0.17 | 0.13 | 2,434,800 B | 31,328 B |
| Retained 4 MiB string field | 2.00 | 1.13 | 6,292,512 B | 6,292,152 B |

Sources: [isolated main profile](results/second-pass/isolated-php85.json),
[isolated extra workloads](results/second-pass/isolated-extra-php85.json),
[paired profile](results/second-pass/paired-php85.json), and
[isolated summary with min/max and cold costs](results/second-pass/isolated-summary.csv).
The [final snapshot](results/second-pass/final-snapshot.json) repeats records and
Stripe after adding compiler-resource failure guards; those guards change cold
failure handling, not the generated successful-path parser.
The same profiles include the previous `window-8192` mode: its 1,000-record
isolated median is 2.87 ms, versus 1.19 ms for `extreme` and 3.99 ms native.

The consistent gains are large record batches, deep graphs and ignored trees.
Four of five real-document fixtures improve; JSON:API's advantage is modest.
Stripe and the recursive-collection fixture remain slower. Large retained strings
do not improve, and the string map uses slightly more allocation. The ignored-string
timing **reverses between harnesses**, so no stable speed claim is justified there;
its warmed temporary-allocation saving is clear. Absolute timings vary greatly
with machine conditions: for example, paired 1,000-record medians were 12.40 ms
native and 3.17 ms direct. Paired ratios reduce temporal drift, not all measurement
bias. Raw samples remain available rather than selecting the most favorable run.

### Cold cost and runtime sensitivity

First-call measurements include decoder class loading and metadata compilation,
but exclude bootstrap/input creation. They are not pure compiler timings:

| Workload | Native cold | Direct cold | Native cold peak | Direct cold peak |
| --- | ---: | ---: | ---: | ---: |
| 1,000 records | 13.95 ms | 11.52 ms | 1,124,304 B | 486,040 B |
| Stripe invoice | 32.91 ms | 36.85 ms | 1,009,816 B | 1,732,576 B |
| GitHub pull request webhook | 20.43 ms | 21.98 ms | 696,048 B | 1,002,760 B |
| Kubernetes deployment | 26.06 ms | 28.81 ms | 743,648 B | 1,109,056 B |
| Ignored tree | 67.39 ms | 58.57 ms | 38,587,448 B | 212,440 B |
| Ignored string | 7.19 ms | 13.92 ms | 6,510,392 B | 6,291,528 B |

The cold ignored-string path still performs native validation before compiling
metadata, so its first-call allocation does not share the warmed saving. Complex
document schemas can cost more memory and time on their first decode. Process-local
caches must not be assumed to survive ordinary request boundaries.

Fifteen-round corroboration on [PHP 8.4.1](results/second-pass/paired-php84.json)
gives ratios of 0.28 for 1,000 records, 0.16 for the deep chain, 0.75 for GitHub,
0.51 for Kubernetes and 1.13 for Stripe. The retained string is 2.01; the ignored
string is 1.16. These support the main workload-dependent conclusion.

With [PHP tracing JIT](results/second-pass/paired-php85-jit.json), the ratios become
0.39 for records, 0.27 for the deep chain, 0.72 for Kubernetes, 1.03 for GitHub,
1.26 for Stripe and 1.16 for scalar lists. Native hydration also benefits from JIT,
so several smaller wins disappear. With [PCRE JIT disabled](results/second-pass/paired-pcre-nojit.json),
small objects are approximately tied, records are 0.55, Stripe is 1.85 and ignored
trees are 2.61. PCRE JIT materially contributes to the retained fast paths.

## What changed

The old window parser specializes one scalar object at a time. The new graph
compiler specializes the complete declaration graph. Its grammar checks JSON
syntax and field types before any target constructor runs. Generated closures then
advance a shared cursor and construct target classes and collections directly.
Large acyclic schemas below the conservative grammar-size bound prove the depth
limit statically, so a large flat record batch no longer needs separate native
validation. Recursive schemas keep the depth guard.

`extreme` first tries fused small-object matching and bounded ignored-subtree
projection. It then tries a compact generated graph when the source appears
compact, a flexible generated graph, and finally the column-window general
parser. Actual grammar matching remains authoritative; the compact heuristic
only chooses which path to attempt. Error and unsupported-shape fallbacks preserve
the existing decoder contract. Production source and its API remain unchanged.

## Further optimization ledger

These experiments extend the [first-pass ledger](RESULTS.md). Optional slower
modes are retained so the decisions can be reproduced on another runtime.

| Experiment | Decision |
| --- | --- |
| Whole-schema typed grammar and generated forward cursor | Retained. Removes repeated schema dispatch, nested span rescanning and generic successful-path validation. |
| Static depth proof for acyclic schemas | Retained. Removes the extra full-input native validation pass on eligible large flat collections. |
| Fused scalar root validation/capture | Retained. Small objects avoid separate validation and generic cursor setup. Captures remain bounded. |
| Bounded ignored-subtree projection | Retained. Validates ignored trees without retaining their values or copying their full text into captures. Unsupported depth/keys fall back. |
| Column capture layout and generated window loop | Retained as general fallback. Reduces per-row capture arrays and factory calls; window size trades allocation for throughput. |
| Compact-schema whitespace elimination | Retained with fallback. Fully matched compact documents avoid repeated whitespace functions. |
| Inline scalar reads and delimiter search | Retained. Removes helper-call overhead in generated object bodies; escaped strings still use the correct slow path. |
| Inline scalar-only record bodies inside list loops | Retained. Removes one closure call per row without extending temporary child-object lifetimes. |
| Bulk scalar-list/map leaves | Retained. Native leaf parsing outperforms PHP loops; float-list values are converted to the declared float representation. |
| Public-property locals and presence masks | Retained under conservative reflection guards. Avoids pair arrays; hooks, readonly/asymmetric setters, magic setters and constructor-created references retain safe assignment behavior. |
| Full-batch capture before construction | Rejected from combined mode. More capture allocation and slower than the inlined cursor loop in the record comparison. |
| ASCII-only projection grammar without UTF-8 prepass | Retained before Unicode projection. Raw non-ASCII bytes reject this path; the Unicode fallback remains available. |
| ASCII-only whole-schema grammar | Optional only. Small record improvement and a larger string improvement do not justify rejecting raw Unicode on the default graph path; large retained strings still lose. |
| Factored member-name trie | Optional only. No consistent improvement over ordinary alternatives. |
| Conditional whitespace skip | Optional only. The extra PHP branch was slower on all three measured documents. |
| Combined PCRE key/colon/whitespace read | Optional only. Slower than inline key reads on all three measured documents. |
| Phase attribution | Validation was a small share of ordinary document cost; construction dominated. This motivated inlining and property-local work. Large strings reverse that balance. |
| PCRE resource-limit stress | Found and fixed unchecked `null` results during regex-based compiler transformations. Compiler failure now takes the fallback; matching restores any temporary limit change. |

Supporting exploratory profiles are saved for
[columns](results/second-pass/columns.json),
[whole-batch captures](results/second-pass/fused-batch.json),
[ASCII projection](results/second-pass/ascii-projection.json),
[ASCII schema](results/second-pass/ascii-schema.json),
[member tries](results/second-pass/member-trie.json),
[inline scalar reads](results/second-pass/inline-scalars.json),
[whitespace/key variants](results/second-pass/lexical-variants.json), and
[phase attribution](results/second-pass/phase-profile.json).
These are development milestones, not interchangeable final-source profiles.
Construction-only phase timings omit validation and are not decoder comparisons.

## Compatibility and limits

The standalone verifier covers 40 compatible configurations, existing acceptance
providers, generated and mutated JSON, Unicode/number/depth boundaries, precise
error contracts, optional defaults and constructor side effects. New cases cover
private/protected setters, reference-bound properties, magic setters, omitted
object defaults and scalar leaves containing apparent closing delimiters. It also
asserts that the flexible graph variants really match all five document fixtures;
output equality alone could otherwise hide an always-falling-back optimization.

Final verification passes **90,022 checks with zero failures** on
[PHP 8.4.1](results/second-pass/verify-php84.json),
[PHP 8.5.11](results/second-pass/verify-php85.json), and
[PHP 8.5 with PCRE JIT disabled](results/second-pass/verify-pcre-nojit.json).
The existing PHPUnit suite passes **538 tests, 1,447 assertions**. Prototype files
pass PHP syntax checks and the repository formatter. The configured production
static-analysis paths exclude this experiment; those tools are not claimed as
verification of generated compiler code. This remains regression evidence, not
a proof for every PHP declaration and arbitrary input.
The resource-limit cases clear prototype compiler caches and warm declaration
metadata before setting limits to 1; the production PHPDoc parser itself also
depends on PCRE and can fail to resolve declarations under those artificial limits.

Duplicate JSON member names remain outside the supported round-trip domain.
Unsupported declarations, escaped member spellings, irregular constructor-field
order and grammar resource limits can take slower fallbacks. Generated PHP is
derived from reflection metadata, never interpolated from input JSON. Process-local
schema caches grow with declarations and compiled variants; this is a prototype
compiler, not a bounded-memory streaming decoder.

All measurements are Windows CLI elapsed times, used as a CPU-cost proxy.
They do not establish Linux/FPM throughput, process RSS, concurrent-request
behavior or hardware CPU counters. The input already exists before measurement,
and the complete result remains in memory. Warmed allocation excludes previously
cached metadata and generated code; cold allocation includes schema setup during
the first decode but excludes the harness/bootstrap and input creation.

The remaining work before production adoption is a deployment and maintainability
decision: measure actual schemas and cold/request lifetimes, choose a smaller set
of fast paths, bound or explicitly manage compiled metadata, and review the
compiler independently. There is no defensible proof that no further optimization
exists. The concrete avenues above were implemented and compared; the final
whitespace/key-reading alternatives yielded regressions rather than further gains.
