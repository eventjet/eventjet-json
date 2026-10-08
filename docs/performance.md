# Performance

[Back to the README](../README.md) · [Development setup](../CONTRIBUTING.md)

The decoder reuses validated field declarations, public-property metadata, resolved
collection declarations, and PHPDoc imports within the current PHP process. Every
incoming value is still checked, including collection shapes, item types, enum values, and nested error paths. Decoded
objects and errors are not cached.

Cache storage grows with the classes and collection fields used by the process,
not with the number of documents decoded. Imports are retained per declaring
class, including its namespace scope. Restart long-running workers after changing
class source files so loaded declarations and cached imports remain consistent.

Run the PHPBench suite with development dependencies installed:

```bash
docker compose exec php composer benchmark
```

The suite separates workloads by purpose:

- **Realistic example documents:** the existing Stripe invoice, GitHub webhook,
  JSON:API compound document, AWS MSK event, and Kubernetes deployment. These
  exercise different class structures, nulls, defaults, enums, maps, and nested
  collections. They are public examples, not a measured distribution of consumer
  traffic. Inputs come directly from the acceptance fixtures; their hydration
  checks and JSON round-trip assertions run outside timing. Discovery lists lazy
  factories; each request loads only its selected document or batch.
- **Synthetic record batches:** 0, 1, 100, and 1,000 records of one class, with
  deterministic varied scalar and nullable values. These expose scaling and
  repeated-class metadata reuse; the document fixtures provide the contrasting
  many-class workloads.
- **Focused diagnostics:** constructor scalars, public properties, enums, enum
  unions, short/long scalar lists, and a 1,000-entry string map. Collection DTOs
  contain only the fields being measured, with no acceptance-test bookkeeping in their constructors.
- **Stress diagnostics:** enum-heavy object and root collections, enum-union
  collections, and recursive collections. Keep these for targeted regressions;
  do not count them as several independent examples of typical consumer traffic.
- **Expected errors:** an unknown enum value and invalid first/last items in a
  1,000-item scalar list. Error checks run after timing. These results are reported
  separately from successful decoding.

Local PHPBench takes five iterations; CI takes 20, in separate CLI processes. `cold` measures one
first decode, including decoder class loading and compilation. `warm` performs
one untimed decode first, then batches repeated decodes: 2,000 for small focused
cases; 10,000 for unknown enum errors and 0/1-record batches; 100 for long scalar lists, the string map,
and list errors; 20 for stress cases; and 200 for documents and larger batches. Counts are fixed for both revisions. Check the raw
batch duration and variation when adding workloads; do not tune counts separately
for a candidate or retry until a desired result appears. Setup and verification
are outside timing. Warm measurements include the cost of replacing the previous
result in the loop.

Performance jobs use an explicit set of required PHP extensions to avoid loading
unrelated extensions in each isolated process.

The primary mode enables OPcache and its normal optimizer, disables JIT, PCOV,
and Xdebug coverage, retains PHPDoc comments, disables the file cache, and sets
file-update protection to zero so newly exported source files can be cached.
The runner checks the actual runtime OPcache state. OPcache-off remains available
for consumers whose runtime does not enable it.

**Fresh PHP-FPM requests** cover the request lifecycle missing from CLI loops.
The harness starts an isolated one-worker pool for each measured revision and
invocation. One discarded request per document warms bytecode; 15 subsequent
requests each time exactly one `Json::decode()` using `hrtime`. Each request has
fresh request-local decoder metadata. With OPcache disabled, class compilation
is included on each request; that mode does not have warm bytecode. Fixture loading, FastCGI transport, and
verification are outside the timer. The harness checks PHP version, OPcache and
JIT state, worker identity, and zero OPcache misses during measured decodes when
OPcache is enabled. It stops the pool before replacing any source files. This
measures decoder time, not HTTP latency or server throughput. It does not model
application preloading, concurrent traffic, or framework startup.

PHPBench category groups also determine CI report sections. Reports keep these
groups separate, with no overall score or workload weighting. Older suites without
category groups appear under "Uncategorized workloads."
A large focused gain is not evidence of the same gain for consumers. Compare
realistic document results before prioritizing an optimization. Peak memory is
whole-process/request memory, not incremental decoder allocation; CLI and FPM
memory figures are not directly comparable.

Run one group, or store a baseline and compare after a code change:

```bash
docker compose exec php composer benchmark -- --group=documents
docker compose exec php composer benchmark -- --group=batches
docker compose exec php composer benchmark -- --group=stress
docker compose exec php composer benchmark -- --group=diagnostic
docker compose exec php composer benchmark -- --group=errors
# Optional comparison for consumers running without CLI OPcache:
docker compose exec php composer benchmark -- --profile=opcache-off --group=warm
docker compose exec php composer benchmark -- --store --tag=before
# Change the implementation, keeping the benchmark and environment the same.
docker compose exec php composer benchmark -- --ref=before --store --tag=after
```

Stored results live in the ignored `.phpbench/` directory. In a Git worktree,
the container also needs read access to the Git metadata directory referenced
by `.git` for PHPBench's repository metadata collection.

Benchmarks run separately from `composer check`. The Performance workflow compares
pull requests' proposed merge commit with its exact target parent on the same
runner. Branch pushes and manual runs compare with the first parent. Both versions
use one frozen workload revision (the workflow revision by default) and its PHPBench configuration and the same
installed dependencies; this isolates source changes rather than measuring
dependency upgrades. The command-line runner defaults to baseline workloads when `--workloads` is omitted;
in that mode, changed candidate workloads also receive a separate verification run. An absent suite at the selected workload revision is reported explicitly,
without a regression verdict. Baseline and candidate revisions only need compatible
source code; they do not need their own benchmark suites.

The comparison runner defaults to `--opcache on` and CLI-only measurements; add
`--opcache off` for the secondary mode. Reports and metadata record the selected
mode; compare timings only within a mode.

Manual Performance workflow runs accept `base`, `candidate`, `workloads`, and `opcache` inputs.
The `opcache` input defaults to `both`; select `on` or `off` to run only one mode.

Use exact commit IDs for reproducible comparisons. The workflow revision supplies
the measurement tooling, while the selected workload revision supplies the frozen benchmark
suite and the selected candidate supplies the code under test. This allows existing
PR heads to be remeasured without adding benchmark-configuration commits to them.
Normal pull-request and branch runs measure both OPcache modes on separate runners,
with separate summaries and artifacts. OPcache on is the primary result; off is
a secondary compatibility measurement. Compare baseline and candidate within each
mode, not absolute timings between jobs on different runners. A failed job does
not cancel the other mode. Native PHPBench assertions gate CLI performance in
both modes; PHP-FPM timings remain advisory, and benchmark errors fail CI.
Both modes include PHP-FPM measurements. CLI and FPM receive the same resolved
settings from the frozen workload configuration. FPM overrides OPcache enablement
for its SAPI and disables timestamp validation for its isolated pool. The FPM
request adapter belongs to the comparison runner; frozen workloads provide only
the document definitions and fixtures, so older workload revisions do not need
an FPM endpoint.

For local PHP-FPM checks, build the optional FPM variant in a separate Compose
project. It uses the same PHP version and project dependencies and exposes no ports:

```bash
docker compose -p json-bench-fpm -f compose.yaml -f benchmarks/compose.yaml up -d --build
docker compose -p json-bench-fpm -f compose.yaml -f benchmarks/compose.yaml exec -e BENCHMARK_FPM_BINARY=php-fpm php bash .github/ci/tests/fpm.sh
```

To include it in a revision comparison, add `--fpm on --workloads REF` to the
comparison command and set `BENCHMARK_FPM_BINARY=php-fpm` in that container. On CI,
the binary is `php-fpm8.4`. Both environments require `cgi-fcgi` from `libfcgi-bin`.
As with PHPBench, local Git worktrees need a read-only mount of their Git metadata
for revision comparisons. The standalone FPM smoke check does not require Git.
The artifact includes per-request timing, payload bytes, worker PID, and runtime
settings alongside the advisory per-request samples. The request summary uses
PHPBench's mode and variation statistics. CLI reports separate realistic documents,
synthetic batches, focused diagnostics, stress cases, and errors.

The gate uses PHPBench's native baseline comparison and assertion:

```text
--file=../results/baseline-N.xml
--assert="mode(variant.time.avg) <= mode(baseline.time.avg) * 1.05"
```

The runner prepares separate baseline and candidate environments once, each with
its own source tree and optimized Composer autoloader. Each prepared tree is moved
to the same runtime path for measurement and restored afterward, avoiding
path-dependent autoloader or filesystem differences. Both use identical frozen
workloads and installed dependencies. CLI and FPM reuse these environments; changed
candidate workloads receive a separate environment for verification.

CI splits the discovered workload list across four runners per OPcache mode,
using the workload index modulo four. Each baseline/candidate pair stays on one
runner; measurements never run concurrently on that runner. Each shard retains
the full discovery list and its assigned workload indices. Only shard one runs
the FPM measurements. Local comparisons remain unsharded unless `--shard 1`,
`--shard 2`, `--shard 3`, or `--shard 4` is specified. Shard one requires
`--fpm on`; the other shards require `--fpm off`.

The final comparison checks validate all four artifacts before publishing one
combined result. They reject missing or duplicate shards, inconsistent revisions,
dependencies or configuration, missing workloads, and incomplete sampling.
Partial reruns reuse measurements from shards that did not rerun. Aggregation
selects the artifact belonging to each shard's latest execution in the same
workflow run, mode, and calibration sample. A newer failed or canceled execution
cannot fall back to an older passing result, even when it leaves no artifact.
A completed regression remains a regression; interrupted measurements cannot
produce a passing comparison. The existing required check names remain unchanged.

The runner discovers workloads with a single-iteration discovery run, then
measures each target workload immediately before its candidate counterpart to
reduce the time between comparable measurements. Each version runs 20 iterations
per workload, preserving cold/warm revolution
and warmup settings. PHPBench estimates the mode of per-decode times and fails
with exit code 2 when any workload is more than 5% slower than its freshly
measured target baseline. There is no stored timing baseline, minimum increase
in microseconds, custom verdict engine, or automatic retry-until-pass policy.
Memory remains advisory. Raw XML, PHPBench's aggregate report, commit IDs,
CPU information, and a dependency fingerprint are archived even on assertion
failure. A changed candidate workload suite runs separately without a baseline
assertion, since its workloads are not comparable.

Use the Performance workflow's manual `calibrate` input to compare the same
commit against itself in five independent repetitions of the complete sharded check.
Each repetition uses four runners per selected OPcache mode (40 measurement
jobs when both modes are selected). Normal PR runs still
compare the target with the proposed merge. Each calibration job archives its
own samples and report for each selected OPcache mode. Calibration compares the
selected candidate with itself, overriding the `base` input.

For same-repository PRs, the workflow maintains one GitHub Actions comment and
updates it after each completed run, including a failed regression gate. It shows
the overall percentage change in decode time, counts of faster/unchanged/slower
workloads, and the lowest and highest changes with target and PR times in
microseconds. The overall figure is the geometric mean of workload timing ratios;
each workload has equal weight. Negative percentages mean less time per decode.

The comment has three comparison outcomes:

- **Performance regression:** PHPBench's regression assertion failed for any workload.
- **Performance improvement:** no regression, and at least one workload is more than 5% faster.
- **No significant performance changes:** all workloads remain within ±5%.

Exactly 5% stays inside the unchanged band. A regression takes priority even when
other workloads improve or the overall figure is faster. This uses the existing
5% operating limit; it is not a statistical significance test. The full run and
artifacts remain linked for inspection. Missing baselines and incomplete runs are
reported explicitly instead of being labeled unchanged. Results for an outdated
PR head or target commit do not update the comment.

Only the separate comment job can write to PRs. Fork and Dependabot PRs still
receive the job summary and artifacts, but skip comments because their workflow
tokens have read-only permissions.

The 5% limit was selected from hosted unchanged-code calibration, not local
machine timings. Two batches of five independent GitHub runners produced 200
workload comparisons:

- [Selection batch](https://github.com/eventjet/eventjet-json/actions/runs/37764898344): largest apparent slowdown 3.22%; a 3% limit would have failed one comparison.
- [Independent validation batch](https://github.com/eventjet/eventjet-json/actions/runs/37765567166): largest apparent slowdown 4.11%; the provisional 4% limit failed one unchanged workload. Reversing the comparisons exposed variation up to 4.59%.

A 5% limit was the lowest whole-percentage limit with no observed false positives
in either direction across these 200 comparisons of the earlier diagnostic suite.
The expanded document suite and OPcache modes need their own calibration before
those observations can be generalized. This is an empirical operating
limit, not a guarantee against future false positives on shared runners.
Changes to sampling or thresholds should be checked with unchanged-code runs on
the CI runner. A consistently noisy benchmark needs more stable measurement,
not a larger limit chosen just to pass a particular PR. The expanded workflow retains
its 15-minute job limit and a 90-second limit per subprocess.

Performance tooling validation runs once in a separate job alongside the measurements.
The existing comparison checks require both the measurements and tooling validation
to succeed, so moving validation does not weaken the merge gate.

The comparison runner is PHP (`.github/ci/performance.php`). PHPStan checks the
orchestration code. A shell integration test exercises the real PHPBench CLI
with its deterministic debug executor to verify improvements, threshold
boundaries, regressions, configuration isolation, and failure artifacts:

```bash
docker compose exec php bash .github/ci/tests/configuration.sh
```

To run a comparison locally, use `php .github/ci/performance.php --base REF
--candidate HEAD` in the project container with read access to the repository's
Git metadata. Move or remove `.perf/` before another comparison.

To prevent merging failed checks, require both `Compare decoder performance (OPcache on)` and
`Compare decoder performance (OPcache off)` in the
repository's branch rules for `tabula-rasa`, and later for `master`. The workflow
already compares against each PR's target branch. An absent benchmark suite is
reported without a verdict, allowing the initial rewrite to merge into `master`.

Compare the same workloads, PHP settings, and dependencies on the same host;
review measurement variation along with percentage changes. PHPUnit retains
deterministic cache-contract tests, including checks that repeated lookups load
metadata only once.

## Runtime validation

Every decode validates incoming values. There is no trusted-input mode that
skips runtime checks. Reconsidering that tradeoff requires comparing checked
and trusted-input decoding with the same metadata optimizations.

The PR comment reports the primary OPcache-on comparison. Both modes retain their full reports in the run artifacts.

## Field-name mapping

`FieldMappingBench` compares equivalent three-field objects with ordinary names
and explicit mappings. It checks the decoded object and emitted JSON outside the
timer. It lives outside the default comparison suite because mapped workloads
require an API that older revisions do not provide. Run it explicitly:

```bash
docker compose exec php vendor/bin/phpbench run tests/Performance/FieldMappingBench.php --iterations=15 --report=aggregate
```

Measurements on October 8, 2026 used PHP 8.4.26, an AMD Ryzen 7 7700, OPcache on,
JIT off, and coverage off. Each warm iteration performs 20,000 operations after
one warmup; each cold iteration performs one operation in a new CLI process.
There are 15 iterations per case. These are local observations, not the CI
performance gate or a prediction for every application.

The optimized implementation caches attribute discovery, including an empty
result for ordinary classes. It checks collisions only for annotated classes.
Public-property metadata already uses JSON names. Only renamed constructors use
a wrapper to translate converted JSON names into PHP argument names; ordinary
constructors retain their existing conversion loop. The serialization trait
reuses its mapping without reflecting the class on each call.

| Warm operation | Ordinary fields (µs) | Mapped fields (µs) |
| --- | ---: | ---: |
| Decode, constructor | 1.089 | 1.249 |
| Decode, properties | 1.111 | 1.131 |
| Encode, constructor | 0.078 | 0.308 |
| Encode, properties | 0.077 | 0.311 |

These PHPBench mode estimates show extra work for mapped constructors and a clear
encoding cost for the trait. The public-property decode difference is smaller
than the observed variation. Prefer matching names when you control the format;
explicit mapping remains useful for external names such as `$ref`. This is not
a blanket claim that every mapped decode is slower. Handwritten serializers were
not benchmarked.

Cold CLI timings also include loading and compiling the decoder or trait. They
should not be read as the cost of one attribute lookup, or compared directly
with warm loops or PHP-FPM requests. In particular, cold encoding starts without
an earlier decode warming mapping metadata.

### Effect on ordinary classes

The baseline is `b5f96db992c9c6b7d5f93f01190b0be79d786cf1`, before field mapping.
Both revisions used the same dependencies, configuration, and fixtures. The local
baseline loader prepended an exported baseline `src` directory to Composer's
namespace map; the candidate used its normal source directory. This introduces
path differences, especially relevant to cold CLI measurements. The regular CI
comparison uses prepared workspaces at the same runtime path.

| Ordinary document | Base warm CLI (µs) | New warm CLI (µs) | Base fresh FPM request (µs) | New fresh FPM request (µs) |
| --- | ---: | ---: | ---: | ---: |
| JSON:API compound document | 28.75 | 29.13 | 422.3 | 474.0 |
| AWS Lambda Amazon MSK event | 7.37 | 7.30 | 242.7 | 274.4 |
| Stripe invoice | 100.20 | 99.42 | 1320.8 | 1411.0 |
| GitHub pull request webhook | 69.33 | 71.99 | 602.5 | 650.4 |
| Kubernetes deployment | 91.25 | 91.05 | 974.6 | 1028.3 |

CLI columns are PHPBench modes from 15 iterations of 200 decodes; FPM columns
are medians of 15 separate requests after warming bytecode. FPM verified zero
OPcache misses during every measured decode, with fresh decoder metadata in each
request.

Warm document estimates stayed within about 4% of the base in this run, with
substantial variation in some workloads. That does not prove zero overhead.
Fresh-request document medians were about 5–13% higher: ordinary classes still
pay for discovering whether attributes exist when metadata is first built.
Cold CLI results likewise include additional source loading. No zero-cost claim
is made for startup or per-request metadata construction. Earlier exploratory
runs varied substantially; the tables describe the final optimized
implementation, not a pass obtained by retrying the CI regression gate.

For a reproducible revision comparison, use the existing comparison runner with
this change's commit as candidate and workload revision, and the baseline commit
above. It measures the ordinary workloads on both revisions, including FPM when
requested. Run the explicit mapping benchmark separately on the candidate; only
its `--variant='plain.*'` cases can run against the baseline API.
