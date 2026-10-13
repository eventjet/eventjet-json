# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-10-12

This release rewrites the library as a typed decoder that pairs with PHP's `json_encode()`. Data classes describe their
shape with native type declarations and PHPDoc, and any supported value survives an encode/decode round trip with its
PHP types and JSON object/array shapes intact. See the [type reference](docs/types.md) for the full rules.

### Added

- `Json::decode()` supports unions that identify one type unambiguously, such as `Person|string|null`, literal `true`
  and `false` fields, and public properties outside the constructor, including inherited ones.
- PHPDoc collection declarations: `list<T>`, `non-empty-list<T>`, positional tuples such as `array{int, string}`, and
  maps declared as `non-empty-array<string, T>` or `ArrayObject<string, T>`. Collections can nest in any combination.
- PHPDoc literal types restrict a field to specific values, such as `@param 'open'|'done' $state` or
  `@param 1|2|3 $priority`. Strings, integers, floats, `true`, `false`, constants, class-constant wildcards such as
  `self::STATE_*`, and backed enum cases work, also inside collections such as `list<'red'|'green'>`.
- Doc comments are read with PHPStan's PHPDoc syntax, including `@phpstan-param`, `@psalm-param`, and `@phan-param`
  tags and their `@var` counterparts, which take priority over plain tags. `array-key` reads as `int|string`.
- `JsonType::array()` and `JsonType::map()` describe JSON arrays and objects at the root:
  `Json::decode($json, JsonType::array(Person::class))`.
- `Json::validateType()` checks a class and every class reachable from it without JSON input, so unit tests can catch
  unsupported declarations before they reach production.
- `MappedJsonFields`, a trait that implements `jsonSerialize()` for classes that rename fields with `#[Field]`.
- Conversion errors name the failing field by path, such as `people[1].name`, and keep the constructor's exception as
  the previous exception.
- `DecodeError::$kind` tells bad input apart from a class that cannot be decoded. The `DecodeErrorKind` enum has the
  cases `InvalidJson`, `UnexpectedRootValue`, `InvalidValue`, `UnsupportedDeclaration`, and `ConstructorFailure`, and
  `getCode()` returns the kind's value. An HTTP API can answer the first three with 400 and the others with 500.

### Changed

- **BREAKING**: PHP 8.4 or later and the Tokenizer extension are required.
- **BREAKING**: `Json::decode()` returns a `DecodeError` on failure instead of throwing a `JsonError`. PHPStan and Psalm
  infer `Person|DecodeError`, so callers must check the result. `DecodeError` extends `RuntimeException`, so it can be
  thrown as before.
- **BREAKING**: `Json::decode()` only accepts a class name or a `JsonType` descriptor. It no longer fills an existing
  object.
- **BREAKING**: `Eventjet\Json\JsonType` is a new class for root collection descriptors. It replaces the JSON validation
  API in `Eventjet\Json\Type\JsonType`.
- **BREAKING**: `#[Field]` now requires the class to implement `JsonSerializable` and serialize the mapped names, for
  example with `MappedJsonFields`. Without that, `json_encode()` would write the PHP names and the result could not be
  decoded again.
- **BREAKING**: `Json::decode()` enforces PHPDoc literal types. 0.2.0 ignored PHPDoc, so `@param 'open'|'done' $state`
  accepted any string.
- **BREAKING**: Declarations that cannot round-trip are rejected with a `DecodeError`, even when the member is missing
  from the JSON:
  - `mixed`, untyped, `object`, and `stdClass` fields. In 0.2.0, `mixed` accepted any value.
  - Generic `array<K, V>` maps, because an empty PHP array encodes as `[]`. Use `ArrayObject<string, T>` or
    `non-empty-array<string, T>`.
  - Native `array` fields without a supported PHPDoc collection declaration.
  - Nested classes that are not final, interfaces, abstract classes, and intersection types.
  - Constructor parameters without a public instance property of the same name.
  - Unions that JSON cannot tell apart, such as two classes, or an enum with its own backing type.
  - Literal unions that cannot restore the original PHP type, such as `1|1.0`, or an enum case with its own backing
    value.
  - Number literals that PHP and PHPStan read differently, such as `0x10`, `052`, or `1e999`, and integer range bounds
    such as `int<0x10, max>` or `int<+5, max>`. Use plain decimal numbers.

### Removed

- **BREAKING**: `Json::encode()`. Use `json_encode()`.
- **BREAKING**: The `#[Item]` attribute. Declare collection items in PHPDoc, such as `@param list<Person> $members`.
- **BREAKING**: `JsonError` and the `Eventjet\Json\Type` namespace (`Array_`, `Boolean`, `Member`, `Null_`, `Number`,
  `Object_`, `String_`, `Union`, `ValidationIssue`, `ValidationResult`).

### Upgrading

Check every result of `Json::decode()`. Code that relied on the exception keeps that behavior by throwing the error:

```php
$person = Json::decode($json, Person::class);
if ($person instanceof DecodeError) {
    throw $person;
}
```

Replace `#[Item]` attributes with PHPDoc, and `array<string, T>` maps with `ArrayObject<string, T>`:

```php
// 0.2.0
#[Item(Person::class)]
public array $members;

// 0.3.0
/** @var list<Person> */
public array $members;
```

Classes that keep `#[Field]` need to implement `JsonSerializable`; adding `use MappedJsonFields;` is enough. Where you
can, rename the property to match the JSON name instead.

If your classes already declare PHPDoc literal types, check that producers only send the declared values. Any other
value now returns a `DecodeError`.

Add a `Json::validateType()` assertion for each class you decode into. It reports declarations this release rejects
without waiting for a payload to reach them.

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

[0.3.0]: https://github.com/eventjet/eventjet-json/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/eventjet/eventjet-json/compare/v0.1.3...v0.2.0
