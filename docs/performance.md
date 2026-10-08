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
  unions, and short/long scalar lists. List DTOs contain only the fields being
  measured, with no acceptance-test bookkeeping in their constructors.
- **Stress diagnostics:** enum-heavy object and root collections, enum-union
  collections, and recursive collections. Keep these for targeted regressions;
  do not count them as several independent examples of typical consumer traffic.
- **Expected errors:** an unknown enum value and invalid first/last items in a
  1,000-item scalar list. Error checks run after timing. These results are reported
  separately from successful decoding.

Local PHPBench takes five iterations; CI takes 20, in separate CLI processes. `cold` measures one
first decode, including decoder class loading and compilation. `warm` performs
one untimed decode first, then batches repeated decodes: 2,000 for small focused
cases; 10,000 for unknown enum errors and 0/1-record batches; 100 for long scalar lists
and list errors; 20 for stress cases; and 200 for documents and larger batches. Counts are fixed for both revisions. Check the raw
batch duration and variation when adding workloads; do not tune counts separately
for a candidate or retry until a desired result appears. Setup and verification
are outside timing. Warm measurements include the cost of replacing the previous
result in the loop.

The primary mode enables OPcache and its normal optimizer, disables JIT, PCOV,
and Xdebug coverage, retains PHPDoc comments, disables the file cache, and sets
file-update protection to zero so newly exported source files can be cached.
The runner checks the actual runtime OPcache state. OPcache-off remains available
for consumers whose runtime does not enable it.

Reports keep these groups separate, with no overall score or workload weighting.
A large focused gain is not evidence of the same gain for consumers. Compare
realistic document results before prioritizing an optimization. Peak memory is
whole-process memory, not incremental decoder allocation.

Run one group, or store a baseline and compare after a code change:

```bash
docker compose exec php composer benchmark -- --group=documents
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
in that mode, changed candidate workloads also receive a separate verification run. An absent baseline suite is reported explicitly,
without a regression verdict.

The comparison runner defaults to `--opcache on`; add
`--opcache off` for the secondary mode. Reports and metadata record the selected
mode; compare timings only within a mode.

Manual Performance workflow runs accept `base`, `candidate`, `workloads`, and `opcache` inputs.
The `opcache` input defaults to `both`; select `on` or `off` to run only one mode.

Use exact commit IDs for reproducible comparisons. The workflow revision supplies
the measurement tooling, while the selected workload revision supplies the frozen benchmark
suite and the selected candidate supplies the code under test. This allows existing
PR heads to be remeasured without adding benchmark-configuration commits to them.
Normal pull-request and branch runs measure both OPcache modes in separate jobs,
with separate summaries and artifacts. OPcache on is the primary result; off is
a secondary compatibility measurement. Compare baseline and candidate within each
mode, not absolute timings between jobs on different runners. A failed job does
not cancel the other mode. Native PHPBench assertions gate CLI performance in
both modes; benchmark errors also fail CI.

The gate uses PHPBench's native baseline comparison and assertion:

```text
--file=../results/baseline-N.xml
--assert="mode(variant.time.avg) <= mode(baseline.time.avg) * 1.05"
```

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
commit against itself on five independent GitHub runners. Normal PR runs still
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
