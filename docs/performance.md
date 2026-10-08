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

The ten scenarios cover scalar objects, public scalar properties, short scalar
lists, lists with 1,000 items per scalar field, named enum fields, enum unions,
collections of enum-union fields, object collections, recursive collections,
and a root array of 100 collection-bearing objects.
PHPBench runs each of five iterations in a separate process:

- `cold`: one decode per iteration, with no warmup. Fixture construction and
  JSON encoding happen before timing without calling the decoder. Decoder class
  loading is included; repeated objects within that first document can reuse
  metadata populated earlier in the same decode.
- `warm`: one untimed decode populates the caches, followed by 200 measured
  decodes per iteration, or five for the large root array.

Every decode checks for errors. After each iteration, outside the timed region,
the last result is re-encoded and checked against the independently constructed
input. PHPBench reports execution time, variation, and memory use. Its child
processes disable PCOV and CLI OPcache through `phpbench.json`.

Run one group, or store a baseline and compare after a code change:

```bash
docker compose exec php composer benchmark -- --group=warm
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
use the target's benchmark fixtures and PHPBench configuration and the same
installed dependencies; this isolates source changes rather than measuring
dependency upgrades. A changed candidate benchmark suite also runs separately
with its own configuration. An absent baseline suite is reported explicitly,
without a regression verdict.

The gate uses PHPBench's native baseline comparison and assertion:

```text
--file=../results/baseline.xml
--assert="mode(variant.time.avg) <= mode(baseline.time.avg) * 1.05"
```

Each version runs 20 iterations per workload, preserving cold/warm revolution
and warmup settings. PHPBench estimates the mode of per-decode times and fails
with exit code 2 when any workload is more than 5% slower than its freshly
measured target baseline. There is no stored timing baseline, minimum increase
in microseconds, custom verdict engine, or automatic retry-until-pass policy.
Memory remains advisory. Raw XML, PHPBench's aggregate report, commit IDs,
CPU information, and a dependency fingerprint are archived even on assertion
failure. A changed candidate workload suite runs separately without a baseline
assertion, since its workloads are not comparable.

The 5% limit is a sensitivity target, not a guarantee against false positives.
Changes to sampling or thresholds should be checked with unchanged-code runs on
the CI runner. A consistently noisy benchmark needs more stable measurement,
not a larger limit chosen just to pass a particular PR. The workflow retains
its five-minute job limit and a 90-second limit per subprocess.

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

To prevent merging failed checks, require `Compare decoder performance` in the
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
