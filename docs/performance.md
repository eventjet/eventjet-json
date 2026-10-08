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

The workflow alternates baseline/candidate order across three pairs, takes five
iterations per invocation, and runs three additional unchanged-code pairs to
estimate noise. Its summary separates cold and warm workloads and shows timing,
variation, paired percentage changes, and process peak memory. Raw samples,
environment details, commit IDs, and an installed-dependency fingerprint are archived for history.
Performance changes are advisory until repeated unchanged-code runs establish a
useful threshold; benchmark errors and the five-minute job limit still fail CI.
Do not treat one noisy runner result as evidence of a regression.

The comparison runner is PHP (`.github/ci/performance.php`); its calculation and
invalid-output tests run with PHPUnit, and a deterministic shell check verifies
configuration isolation. The calculation contracts are checked by PHPStan with
shared array-shape aliases in `phpstan.neon`. To run a comparison locally, use
`php .github/ci/performance.php --base REF --candidate HEAD` in a Linux environment
with PHP, Composer, Git, and GNU coreutils available (such as the project container).
Move or remove `.perf/` before another comparison.

Compare the same workloads, PHP settings, and dependencies on the same host;
review measurement variation along with percentage changes. PHPUnit retains
deterministic cache-contract tests, including checks that repeated lookups load
metadata only once.

## Runtime validation

Every decode validates incoming values. There is no trusted-input mode that
skips runtime checks. Reconsidering that tradeoff requires comparing checked
and trusted-input decoding with the same metadata optimizations.
