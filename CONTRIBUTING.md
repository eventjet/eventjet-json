# Contributing

[Back to the README](README.md)

## Local development

Build and start the PHP 8.4 development container. On Linux, passing your user
and group IDs keeps files created in the container owned by your host user.

```bash
LOCAL_UID="$(id -u)" LOCAL_GID="$(id -g)" docker compose up --build --detach
docker compose exec php composer install
```

Run the complete quality suite inside the container:

```bash
docker compose exec php composer check
```

Keep formatting, dependency checks, static analysis, PHPUnit, and mutation
testing passing as support grows. These are ongoing development requirements.

### PHPDoc parity

The decoder reads doc comments like phpstan/phpdoc-parser 2.3.6 as PHPStan
2.3.1 sets it up (see [Reading PHPDoc](docs/types.md#reading-phpdoc)). The
`parity` test suite checks this against the real phpdoc-parser, which is a
development dependency pinned to that version. It also checks that the fast path
for simple doc comments reads the same tags as the full parser. `composer check`
runs it, and so does its own CI job. Mutation testing does not run it.

```bash
docker compose exec php composer parity
```

It compares every doc comment and type that the test suite declares, fixed
examples, and generated doc comments with fixed seeds, so each run compares the
same inputs. It takes about 1.5 minutes. To compare many more generated doc
comments, run the sweep, which takes about 10 minutes:

```bash
docker compose exec php composer parity-sweep
```

`PHPDOC_PARITY_SCALE` sets the size of the generated corpora: the sweep uses 10,
and the default is 1. A larger scale compares the same inputs first, then more.

When you upgrade phpdoc-parser or PHPStan, update the pinned version and the
parser together. The only differences the suite accepts are the number literals
that the decoder rejects on purpose. They are listed in
`tests/Parity/NumberLiterals.php`.

Stop the development container when you are finished:

```bash
docker compose down
```

See [Performance](docs/performance.md) for benchmarks and comparison procedures.

For background on the decoder design, see the
[direct JSON parsing investigation](docs/research/direct-json-parsing.md).
