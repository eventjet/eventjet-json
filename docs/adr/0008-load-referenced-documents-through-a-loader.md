# Load referenced documents through a loader, never fetch them

The validator never does I/O. Documents that `$ref` and `$schema` point to come from a loader, a closure that the
caller passes to `Validator::compile()` and `JsonSchema::decode()` (decided on 2026-11-04,
[#124](https://github.com/eventjet/eventjet-json/issues/124)). The official meta-schemas are built in.

A validator that fetches URLs lets a schema make the server request addresses that an attacker chooses, and it would
need an HTTP client, timeouts, and caching that callers already have their own opinions on. With a loader, callers
decide where documents come from (disk, HTTP, a cache) and which sources are allowed.

## Consequences

- `compile()` calls the loader for every URI that it can't resolve, including URIs in loaded documents and the
  `$schema` of custom meta-schemas, and never while values are checked. So validation does no I/O, and `compile()`
  reports every unresolvable reference before any value is checked.
- The loader returns schema objects, so each loaded document follows the rules of its own class.
- The library keeps no cache of documents or compiled validators. Callers keep and reuse the `Validator`.
