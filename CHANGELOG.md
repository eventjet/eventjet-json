# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-08-04

### Changed

- **BREAKING**: `Json::decode()` now rejects values whose JSON type does not match the declared PHP type instead of
  coercing them. Both promoted constructor parameters and plain properties throw a `JsonError` naming the parameter or
  property, its class, the expected type and what arrived:

  ```
  Expected bool for parameter "pinRequired" of class Body, got string
  ```

  Previously, values bound to constructor parameters were passed through unchecked. The instance is created through the
  Reflection API, which always binds arguments in weak mode regardless of any `declare(strict_types=1)`, so PHP silently
  coerced them: `{"pinRequired":"not a boolean"}` decoded to `true`, `{"code":42}` to `'42'`, and `{"articleType":50.9}`
  to `50` plus a deprecation notice. Decoding reported success while handing the caller a value the payload never
  contained.

  The check mirrors strict-mode parameter binding:

  | Declared type | Accepted | Rejected |
  | --- | --- | --- |
  | `bool` | `true`, `false` | everything else, including `"true"`, `"false"`, `0`, `1` |
  | `int` | int | float (including `50.0`), numeric string, bool, everything else |
  | `float` | float, **and int** | numeric string, bool, everything else |
  | `string` | string | int, float, bool, everything else |
  | `true` / `false` / `null` | exactly that value | everything else |
  | `mixed` | anything | – |

  Widening an int to a float keeps working, because that is the one conversion strict mode itself performs: a payload
  sending a whole amount as `100` still decodes into `float $amount` as `100.0`.

  Nullable parameters and properties still accept `null`, non-nullable ones no longer do, and omitted optional
  parameters still take their default without being checked.

- **BREAKING**: Constructor parameters and properties typed `iterable`, `object` or `callable` are now rejected with
  `Unsupported type "iterable" for parameter "value" of class …` instead of being passed through. `json_decode()` cannot
  produce a value that meaningfully satisfies them.

### Upgrading

Payloads that decode today may start throwing `JsonError` at runtime rather than failing analysis. Codebases are likely
to depend on the old coercion without knowing it, because the library was already strict about the very same JSON when
the target field happened to be a plain property rather than a promoted constructor parameter:

```php
final class ViaCtor { public function __construct(public bool $flag = false) {} }
final class ViaProp { public bool $flag = false; }

Json::decode('{"flag":"not a boolean"}', ViaCtor::class)->flag;
// before: true — after: JsonError
Json::decode('{"flag":"not a boolean"}', ViaProp::class)->flag;
// before: TypeError — after: JsonError
```

One input produced two answers, decided by a detail of the target class that has nothing to do with the JSON. Both paths
now fail the same way, so a codebase may have been relying on the lax behavior in one place and not in another.

If a producer legitimately sends a scalar in a different shape than the consumer declares — numbers as strings, for
instance — declare the parameter with the type that is actually sent and convert it in your own code.

[0.2.0]: https://github.com/eventjet/eventjet-json/compare/v0.1.3...v0.2.0
