# Direct parsing: results and optimization ledger

Direct construction can reduce temporary allocation substantially and can beat
the current decoder for homogeneous scalar-object collections. It does not beat
`json_decode()` plus hydration consistently. Keep the production decoder as the
default; this prototype is a reproducible experiment, not a production replacement.

The experiment uses PR #30 at `d56aa024ce0db5411addb61298b4da4be021d39b` as
the baseline. Production source, public APIs, and the PHPUnit suite are unchanged.
The parser, verifier, benchmarks, and saved measurements live in this directory.

## Representative comparisons

Five fresh-process samples, Windows, PHP 8.4.1, CLI OPcache/JIT disabled.
Times are warmed elapsed-time medians. Peak allocation includes the returned
graph but excludes input storage and warmed metadata. These are allocator bytes,
not process RSS. The compatible direct mode below is `window-8192`.

| Workload | Native time | Direct time | Native peak | Direct peak |
| --- | ---: | ---: | ---: | ---: |
| Scalar object | 6.01 us | 8.56 us | 1,064 B | 808 B |
| 1,000 scalar records | 4.13 ms | 2.85 ms | 812,128 B | 278,144 B |
| Long scalar lists | 1.39 ms | 2.50 ms | 143,648 B | 131,728 B |
| Ignored 50,000-record tree | 55.67 ms | 56.40 ms | 37,853,536 B | 1,264 B |
| Ignored 4 MiB string | 4.60 ms | 6.53 ms | 6,292,576 B | 1,216 B |

The 1,000-record case improves the median by **31%** and reduces incremental
peak allocation by **66%**. Timing varies: native samples range from 3.87 to
9.45 ms, direct samples from 2.73 to 3.43 ms. These are observations on this
machine, not a statistical claim about every deployment.
See [PHP 8.4 samples](results/php84-cli.json) and
[the summary including dispersion](results/summary.csv).

PHP 8.5.11 shows the same workload-dependent pattern. Its saved main profile has
1,000 records at 9.27 ms native versus 6.64 ms direct, and 10,000 records at
151.19 ms versus 62.66 ms, with peak allocation falling from 8,239,648 B to
2,274,696 B. Absolute timings differ substantially across benchmark runs;
do not interpret the largest percentage as a universal speedup.

For representative real documents, the native decoder remains faster. PHP
8.5.11, five samples, no CLI OPcache/JIT:

| Document | Native | Direct window | Direct custom validation | Native peak | Direct peak |
| --- | ---: | ---: | ---: | ---: | ---: |
| JSON:API compound document | 249 us | 542 us | 464 us | 25,192 B | 14,352 B |
| AWS Lambda Amazon MSK event | 46 us | 139 us | 145 us | 8,528 B | 6,624 B |
| Stripe invoice | 672 us | 1,863 us | 1,748 us | 70,816 B | 39,336 B |
| GitHub pull request webhook | 745 us | 1,254 us | 1,111 us | 51,536 B | 25,848 B |
| Kubernetes deployment | 887 us | 1,793 us | 1,779 us | 57,848 B | 28,288 B |

Source: [PHP 8.5 main profile](results/php85-cli.json). The allocation savings
come from retaining source spans and constructing typed objects without a whole
generic tree. The general PHP control flow still costs more than native parsing.

The [final-source spot check](results/final-snapshot.json) repeats record batches,
ignored payloads, and the GitHub document. It also shows why validation modes
matter: on PHP 8.5 the native-validation window path still peaks near 6 MiB for
an ignored 4 MiB string; custom validation reduces that to about 1.2 KiB, but
takes longer. Native-validation scratch allocation differs between PHP builds.

## Optimization ledger

The verifier exercises 17 compatible parser configurations. The benchmark also
includes deliberately incompatible diagnostic controls. Earlier profiles capture
optimization milestones; they are not all measurements of identical final source.
Use the runnable harness to measure the final implementation in another environment.

| Attempt | Outcome and retained decision |
| --- | --- |
| Pure PHP lexer, Unicode decoding, and recursive type-directed parsing | Correct on the verification corpus but markedly slower. Retained as a comparison mode. Even with tracing JIT, the 1,000-record pure mode takes about 21.5 ms versus 6.9 ms native. |
| Native decoding of escaped string tokens | Avoids expensive PHP unescaping on the common escaped-string path. Preserve pure unescaping for comparison. Decoding every plain string natively adds call overhead. |
| Native bulk decoding of scalar lists/maps | Native collection parsing wins over repeated PHP scalar-token handling. Keep native bulk conversion for these leaves; a direct class graph need not use a pure PHP lexer for every token. |
| Source spans for children and ignored members | Avoids retaining a complete generic tree. Validate constructor scalar fields before child construction and process constructor arguments in declaration order. |
| Possessive PCRE subtree scanner | Moves byte scanning into native PCRE. An empty end-offset match avoids copying ignored payloads. Retain a general scanner fallback on resource failure. A bounded temporary work budget is restored before user code. |
| Cached schema plans | Reuse reflection, constructor parameter validation, field converters, and public-property metadata. Useful for repeated classes, insufficient alone to close the general parsing gap. |
| Compiled scalar/enum/union schemas and constructor factories | Largest targeted CPU gain for repeated record shapes. Factories use reflection metadata, positional arguments, cached strict enum cases, and direct scalar conversion. Fallback occurs before construction. |
| Ordered versus unordered objects | Declaration-order fast paths reduce dispatch. General fallback preserves arbitrary key order, defaults, escaped keys, errors, and public-property hook order. Unordered public assignment optimization is disabled because assignment order can be observable. |
| Named versus numbered captures | Numbered captures reduce intermediate capture bookkeeping. Both variants remain measurable. |
| Object chunks and byte windows | Byte windows remove an extra chunk-boundary scan. Tried chunk sizes 16, 64, 128, 1,024 and byte windows 4,096, 8,192, 32,768, 131,072. An 8 KiB window balances allocation and throughput; no unique timing winner. A 131 KiB window can allocate as much as the native tree. |
| Bounded custom JSON validation grammar | Avoids native validation's large-string scratch allocation on eligible inputs, while checking number grammar, UTF-8, escapes, surrogate pairs, and whitespace. Resource failures and potentially deep inputs use native validation. It is sometimes faster, sometimes slower. |
| Omit prevalidation, assuming trusted valid input | Diagnostic only. Speeds up record batches and ignored trees substantially, demonstrating validation cost. Rejected for compatible decoding: malformed ignored/trailing input must fail before any target constructor runs. |
| Limit large scalar-object captures | General parsing handles objects outside the 32 KiB fast-path extent guard, avoiding large regex capture copies. A retained 4 MiB string still costs approximately 6 MiB because the output itself dominates. |
| Defer error-path construction | Linked breadcrumbs reduce allocation for deep object graphs, but do not reliably improve CPU time. Kept as an optional mode. |
| Index container ends once | Bounded to 64 KiB and 1,024 opening delimiters. For depth 384, lowers direct time from 7.54 ms to 3.36 ms (3.17 ms with custom validation), still slower than native at 1.25 ms. Flat documents pay unnecessary index cost, so indexing remains optional. |
| Tracing JIT | Tested with a 64 MiB JIT buffer. The 1,000-record window median becomes 6.27 ms versus native 6.90 ms, with overlapping sample ranges. JIT does not make the pure parser competitive. |
| Disable garbage collection | No reliable benefit across the collection controls; sometimes slower. Normal modes preserve GC settings. |
| Native JSON plus hand-written record constructor | Diagnostic reaches about 1.99 ms versus production 4.00 ms and direct window 3.13 ms in the tuning run. This shows construction specialization explains part of the gain; it does not establish that PHP tokenization beats native parsing. It retains the generic tree and omits the general declaration/error contract. |

Profiles supporting the ledger:
[pure comparison](results/pure-comparison.json),
[chunk tuning](results/chunk-tuning.json),
[validation controls](results/validation-controls.json),
[deferred paths and large inputs](results/deferred-paths.json),
[container indexing](results/offset-index.json),
[JIT](results/php85-jit.json), and [GC controls](results/gc-control.json).

## Compatibility and verification

The standalone verifier passes **21,020 checks with zero failures** on both PHP
8.4.1 and 8.5.11. It reuses existing acceptance providers and adds seeded generated
objects, syntax mutations, numeric overflow and signed-zero boundaries, Unicode,
invalid UTF-8, nesting limits, large strings, chunk boundaries, aliases,
constructor exceptions and side-effect ordering, and PHP 8.4 property hooks.
Comparison checks include the complete decoded graph or the precise error contract.

One important discovery: `json_validate()` accepts a leading-NUL object property
name that `json_decode()` rejects. The prototype checks keys even in ignored
subtrees and delegates malformed documents to the native path to preserve error
precedence. Syntax validation always finishes before construction in compatible
modes. Existing converters remain authoritative where declaration rules require
them; some fallback and error paths decode a single subtree.

The existing PHPUnit suite passes **538 tests, 1,447 assertions**. PHPStan and
Psalm pass for their configured project paths; those paths do not include this
isolated prototype. Repository Mago formatting, lint, and analysis also pass.
Prototype PHP files are formatted and syntax checked separately. This is strong
regression evidence, not a proof over every PHP declaration or arbitrary JSON.
The production dependency checker also reports no unknown symbols.

## Measurement boundaries and next decision

Each sample runs in a fresh process, modes are shuffled deterministically, and
the complete result is checked against native output outside the timed region.
The worker records first-call cost, warms ten calls, measures allocation, then
calibrates a repeated timing batch to approximately 100 ms. Cold values include
decoder metadata and class loading inside the first decode. Raw files retain all
samples; the CSV exposes min/max rather than hiding dispersion.

Measurements are Windows CLI results. Linux/FPM, other architectures, concurrent
requests, RSS, and hardware CPU counters have not been measured. Peak allocation
is `memory_get_peak_usage(false)` relative to the pre-decode allocator usage;
the input string already exists. The returned object graph stays fully resident.
This is neither a streaming API nor a constant-memory decoder. Class caches grow
with encountered declarations, and schema compilation has a cold cost.

The experiments exhaust the concrete PHP-level optimization avenues investigated
here: scalar/token handling, scanning, validation, schema dispatch, construction,
capture layout, batch sizing, deep traversal, JIT, and GC. A native extension or a
different streaming/output contract would be a separate implementation project.
The evidence supports a memory-focused direct parser and selective specialized
record paths, but does not support replacing the default decoder. Native parsing
with faster general hydration is also worth investigating independently, given
the hand-written construction control.

To regenerate the CSV after saving another profile:

```sh
php experiments/direct-json/summarize.php experiments/direct-json/results/*.json > experiments/direct-json/results/summary.csv
```

Expand the JSON paths explicitly on shells that do not expand wildcards.
