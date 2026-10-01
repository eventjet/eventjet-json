# eventjet/json

Type-safe JSON decoding for PHP.

## Installation

Install the package with Composer:

```bash
composer require eventjet/json
```

## Object decoding

Pass a JSON object and the concrete class to construct to `Json::decode()`. JSON
member names must match the class's constructor parameter names.

```php
<?php

declare(strict_types=1);

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;

final readonly class Person
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public int|null $age = null,
    ) {}
}

$person = Json::decode(
    '{"firstName":"Ada","lastName":"Lovelace","age":36}',
    Person::class,
);

if ($person instanceof DecodeError) {
    throw $person;
}

echo $person->firstName;
```

Decoding failures are returned as `DecodeError` values rather than thrown by
`Json::decode()`. Check the result before using the decoded object. The error
message describes the invalid JSON, unexpected root value, type mismatch, or
construction failure; `getPrevious()` retains an underlying construction
exception when one exists.

Only the types and class shapes marked as supported below are part of the
current contract. In particular, the root value must be a JSON object.

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

Stop the development container when you are finished:

```bash
docker compose down
```

The `tabula-rasa` rewrite treats `Json::decode()` as the inverse of PHP's
`json_encode()`: supported values must round-trip without changing their meaning
or shape. Target classes should use standard PHP types and PHPDoc rather than
package-specific attributes. Decoding failures are returned as `DecodeError`
values.

## Explicitly unsupported

These are deliberate exclusions from the rewrite's supported behavior. Some
rejection paths still need implementation and tests. Keep this list updated as
scope decisions are made, and include a reason for every exclusion.

| Feature or value | Why it is unsupported |
| --- | --- |
| Unions containing more than one class, such as `ClassA\|ClassB` | JSON objects do not identify their PHP class. Choosing a class would require a discriminator or inspecting class fields, adding complexity to decoding. |
| Unions containing more than one enum | The union contract allows at most one enum. Multiple enums would need additional selection rules, especially when their backing values overlap. |
| Unions combining a backed enum with its backing scalar type, such as `MyStringBackedEnum\|string` or `MyIntBackedEnum\|int` (initially) | An enum case and its backing scalar encode to the same JSON value. Checking the enum first would turn an original scalar into an enum case; checking the scalar first would lose the enum case. JSON cannot recover the original PHP type, so these unions break the round-trip contract. |
| Interface and abstract class type hints | They cannot be instantiated directly, and JSON does not identify which concrete implementation or subclass to create. Resolving one would require additional selection rules or metadata. |
| Intersection type hints | JSON does not identify the concrete class that satisfies every member of an intersection. Resolving one would require additional selection rules or metadata. |
| Non-backed enums | They have no scalar backing value and cannot round-trip through PHP's `json_encode()`. |
| Non-JSON-encodable values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references | JSON cannot represent these values without losing information or changing their meaning. They fall outside the round-trip contract. |
| Implicit coercion of mismatched scalar types | Converting values such as `"42"` to `42` hides a type mismatch and changes the value's type. Decoding must validate the declared type. |

Other open decisions remain in the TODO list until their scope is settled.

## Numeric round trips

Integer fields support values from `PHP_INT_MIN` through `PHP_INT_MAX`. PHP
decodes JSON integers outside that platform range as floats, so they are
rejected instead of being rounded or coerced back to integers.

Float fields use PHP's native floating-point precision. The default
`json_encode()` and `json_decode()` behavior preserves finite values, including
subnormal values and precision-sensitive decimals. JSON does not distinguish a
whole-valued float such as `3.0` from the integer `3`; the declared field type
restores the value to a PHP float during construction.

## Supported types and limits

The table describes the behavior that is currently covered by the acceptance
suite. "Not yet supported" means that the behavior remains on the roadmap and
must not be relied on, even if a particular value happens to decode.

| Target or value | Status | Limits |
| --- | --- | --- |
| Concrete classes | Supported at the root | The JSON root must be an object. Its member names are passed as named constructor arguments, so they must match the constructor parameter names. |
| Readonly classes | Supported at the root | The same constructor rules as other concrete classes apply. |
| `string`, `int`, `float`, and `bool` constructor fields | Supported | Values must have the declared type. An integer JSON value is also valid for a `float` field because the declaration restores it as a PHP float. Other implicit scalar coercions are rejected. |
| `null`, nullable scalar fields, and literal `true` and `false` fields | Supported | A non-null value must still match the non-null member of a nullable type. Literal Boolean fields accept only their declared value. |
| `array` constructor fields | Limited | Decoded arrays are passed through, but PHPDoc item and value types are not read or validated. Only nonempty, string-keyed maps have round-trip coverage. |
| Nested class fields | Not yet supported | JSON objects are not yet converted to the field's declared class. |
| Backed enum fields | Not yet supported | Backing values are not yet converted to enum cases. Non-backed enums are rejected because they have no JSON representation. |
| General union types | Not yet supported | Nullable scalar declarations are supported. Other unions do not yet have a supported selection policy. |
| Intersection types | Rejected | JSON does not identify a concrete class that satisfies the intersection. |
| Interfaces and abstract classes | Rejected at the root and in field types | Root targets, direct constructor field declarations, and union members return dedicated errors because JSON does not identify a concrete implementation or subclass to instantiate. Rejection for collection item and value types remains to be added. |
| Public properties outside the constructor, inherited properties, and classes without usable constructors | Not yet supported | Object construction currently uses named constructor arguments only. |
| `mixed`, untyped fields, `object`, and `stdClass` | Not yet supported | Their decoding and round-trip policies have not been defined. |
| Root arrays, maps, scalars, enums, and `null` | Not yet supported | `Json::decode()` currently accepts only a JSON object and a class target. |
| Classes implementing `JsonSerializable` | Not yet supported | A custom JSON representation may not correspond to constructor parameters or object properties. |

The round-trip contract also has these representation limits:

- The source object must be encodable by PHP's `json_encode()`. Resources,
  non-finite floats, invalid UTF-8, and circular references are outside the
  supported input domain.
- JSON does not retain PHP class identity. It cannot choose between multiple
  possible classes without an additional discriminator or selection rule.
- A backed enum case and its backing scalar encode to the same JSON value, so a
  union containing both cannot recover the original PHP type.
- The current associative decoding step cannot preserve every nested object and
  array distinction. In particular, empty objects and empty arrays both become
  empty PHP arrays, and numeric-looking object keys may become integer array
  keys.
- Missing object members and members explicitly set to `null` are distinct in
  JSON, but their construction and re-encoding policy is not yet defined for
  optional and defaulted parameters.

## TODO

### Scalar fields and validation

- [x] Test all scalar field types: `string`, `int`, `float`, and `bool`, plus `null`, nullable types, and literal `true`/`false` types.
- [x] Reject field values that do not match the declared type, including values that reflection would otherwise silently coerce.
- [x] Test numeric boundaries and define how to preserve whole-valued floats, large integers, and precision during round trips.
- [x] Test strings containing Unicode, escaped characters, empty strings, and numeric-looking text.
- [ ] Test rejection of non-JSON-encodable object field values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references. Define the validation boundary; the current API uses PHP's `json_encode()` for encoding.

### Objects and construction

- [ ] Decode nested JSON objects into their declared classes, including readonly classes and nullable object fields.
- [ ] Support public properties that are not promoted constructor parameters, including classes without constructors and inherited properties.
- [ ] Decide and test how omitted JSON object members interact with required constructor arguments, optional arguments, defaults, and explicit `null`. Distinguish a missing member from a present null value, and decide whether re-encoding may include initialized default-valued properties or must preserve the original omissions, including whether that requires support beyond round trips that start with a PHP object.
- [x] Test that JSON member order does not affect constructor argument binding.
- [ ] Preserve the distinction between JSON objects and arrays, especially `{}` versus `[]` and objects with numeric-looking keys.
- [ ] Define and test the policy for unknown JSON fields and duplicate JSON member names.
- [x] Reject interface and abstract class root targets with a clear `DecodeError`.
- [x] Reject direct interface and abstract class field types with a clear `DecodeError`.
- [x] Reject interface and abstract class union members with a clear `DecodeError`.
- [ ] Reject interface and abstract class collection item/value types with a clear `DecodeError`.
- [ ] Define supported class shapes and report unsupported ones clearly, including inaccessible constructors and constructor arguments that cannot be recovered from encoded properties.
- [ ] Define behavior for `mixed`, untyped fields, `object`, and `stdClass`.
- [ ] Support unions with at most one concrete class and one backed enum, alongside scalar and null members whose types do not overlap the enum's backing type, such as `Foo|string|int` and `MyEnum|MyClass|null`.
- [ ] Reject unions containing multiple classes (such as `ClassA|ClassB`) or multiple enums. Select the class member by JSON value kind without discriminators or inspecting class fields.
- [ ] Initially reject union declarations combining a backed enum with its backing scalar type, such as `MyStringBackedEnum|string` and `MyIntBackedEnum|int`, with a clear `DecodeError`. Apply this rule to larger and nullable unions and reject the declaration regardless of the input value.
- [ ] Test supported enum/scalar unions with distinct JSON types, such as `MyStringBackedEnum|int` and `MyIntBackedEnum|string`, without coercion.
- [x] Define support or explicit rejection for intersection types.
- [ ] Define the round-trip contract for classes implementing `JsonSerializable`, whose JSON representation may differ from their properties.

### Enums

- [ ] Support string-backed and int-backed enums in object fields.
- [ ] Reject unknown enum values and values of the wrong backing type without coercion.
- [x] Reject non-backed enums with a clear error.
- [ ] Test nullable enums and enums nested inside objects, lists, and maps.

### Lists, maps, and root values

- [ ] Decode JSON array fields into PHPDoc `list<T>`, supporting scalar, enum, and object items.
- [ ] Decode JSON object fields into PHPDoc `array<array-key, T>`, supporting scalar, enum, and object values.
- [ ] Support recursively nested lists and maps, including nullable items and values and unions that follow the same rules as object fields.
- [ ] Validate collection items against their declared types and reject object/array shape mismatches.
- [ ] Test empty collections and map keys, including integer keys, numeric-looking string keys, and non-sequential keys; preserve JSON shape during round trips.
- [ ] Resolve PHPDoc collection types using the declaring namespace and imported or aliased class names.
- [ ] Report missing, malformed, unknown, or unsupported collection type declarations clearly.
- [ ] Add an API for typed JSON arrays at the root, with accurate generic return types for static analysis.
- [ ] Support typed maps at the root and define whether scalar, enum, and null root values are supported.

### Errors and acceptance coverage

- [ ] Keep every decoding failure on the returned `DecodeError` path, including nested conversion failures and exceptions thrown by constructors.
- [ ] Include the failing field, list index, or map key path and expected/actual type in conversion errors.
- [ ] Test stable error codes, useful messages, and preservation of underlying exceptions.
- [x] Expand invalid-JSON coverage to malformed syntax, trailing content, invalid UTF-8, and nesting-depth limits.
- [x] Test rejection of incompatible root shapes; JSON arrays must not be passed to object constructors as positional arguments.
- [ ] Add acceptance round trips for every supported type and combinations of nested objects, lists, maps, and enums; assert both value types and JSON shape.
- [ ] Keep formatting, dependency checks, static analysis, PHPUnit, and mutation testing passing as support grows.

### Documentation

- [x] Document installation, object decoding, and handling returned errors.
- [ ] Document collection type declarations and root collections when they are supported.
- [x] Publish the supported-type matrix and explicit limits, including any values whose original PHP type or shape cannot be recovered from JSON alone.
