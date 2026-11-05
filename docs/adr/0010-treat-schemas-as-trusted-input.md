# Treat schemas as trusted input and values as untrusted

The validator guards against hostile values, but not against hostile schemas (decided on 2026-11-04,
[#124](https://github.com/eventjet/eventjet-json/issues/124)). Values are what clients send, so a value that isn't
JSON, or that is nested too deeply, returns an error value instead of crashing. Schemas come from the application
itself or from sources it chose.

Defending against hostile schemas would need regex timeouts, limits on schema size and reference depth, and a
backtracking-safe regex engine, which PHP doesn't have. Each of these would cost time on every compile or on every
match, for a threat that most callers don't have.

## Consequences

- The docs say that a hostile `pattern` can make PCRE backtrack for a long time, and that schemas should only come
  from trusted sources.
- `compile()` rejects schemas only where the validator would otherwise have to guess, such as an unresolvable
  reference or a `$ref` loop that never moves into the value. It doesn't check a schema against its meta-schema.
