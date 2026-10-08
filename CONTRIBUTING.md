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

Stop the development container when you are finished:

```bash
docker compose down
```

See [Performance](docs/performance.md) for benchmarks and comparison procedures.

For background on the decoder design, see the
[direct JSON parsing investigation](docs/research/direct-json-parsing.md).
