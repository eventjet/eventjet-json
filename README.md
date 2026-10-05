# eventjet/json

Type-safe JSON decoding for PHP.

## Installation

Install the package with Composer:

```bash
composer require eventjet/json
```

## Object decoding

Pass a JSON object and the concrete class to construct to `Json::decode()`.
Members used to construct or hydrate the class must match its constructor
parameter or public property names; additional JSON members are ignored.

The exact match for recognized members is deliberate. Target classes model the
JSON wire format, not the application's domain model. The library does not
rename members or support aliases and naming strategies. Consumers should
decode into transport classes that fit the JSON and then explicitly transfer
the data into their domain objects.

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
| Unions containing multiple enums with overlapping backing values | The same JSON scalar would identify cases from more than one enum, so decoding could not recover the original enum type. |
| Unions combining a backed enum with its backing scalar type, such as `MyStringBackedEnum\|string` or `MyIntBackedEnum\|int` (initially) | An enum case and its backing scalar encode to the same JSON value. Checking the enum first would turn an original scalar into an enum case; checking the scalar first would lose the enum case. JSON cannot recover the original PHP type, so these unions break the round-trip contract. |
| Interface and abstract class type hints | They cannot be instantiated directly, and JSON does not identify which concrete implementation or subclass to create. Resolving one would require additional selection rules or metadata. |
| Non-final class type hints in object fields | A field may contain a subclass of its declared type, but JSON does not identify that runtime class. Reconstructing the declared class could silently change the value. Root targets are unaffected because the caller supplies their exact class. |
| Intersection type hints | JSON does not identify the concrete class that satisfies every member of an intersection. Resolving one would require additional selection rules or metadata. |
| Non-backed enums | They have no scalar backing value and cannot round-trip through PHP's `json_encode()`. |
| Object targets implementing `JsonSerializable` | Their custom JSON representation may not match their constructor parameters, so generic decoding cannot guarantee the round-trip contract. |
| Constructor parameters without same-named declared public instance properties | The class shape does not provide a stable JSON member from which decoding can recover those argument values. |
| Non-JSON-encodable values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references | JSON cannot represent these values without losing information or changing their meaning. They fall outside the round-trip contract. |
| JSON objects with duplicate member names | They cannot be produced by `json_encode()` from supported PHP values. PHP's decoder does not report them separately, and detecting them would require reparsing every document. Callers must not rely on which duplicate value is retained. |
| Implicit coercion of mismatched scalar types | Converting values such as `"42"` to `42` hides a type mismatch and changes the value's type. Decoding must validate the declared type. |
| Member-name mapping, aliases, and naming strategies | Target classes are expected to match the JSON wire format exactly. Transforming decoded transport data into an application's domain model is the consumer's responsibility. |
| `mixed`, untyped, `object`, and `stdClass` constructor fields | These declarations do not provide enough type information to restore every value's original PHP type and JSON shape. In particular, `object` omits the concrete class, while `mixed`, untyped fields, and `stdClass` members can contain PHP arrays that encode as JSON objects. |
| Generic `array<TKey, TValue>` map declarations | An empty PHP array encodes as `[]`, so it cannot represent an empty JSON object. Depending on its runtime keys, a nonempty PHP array can also encode as either an array or an object. Use `non-empty-array<string, TValue>` for a map that cannot be empty or `ArrayObject<string, TValue>` for a map that may be empty. |

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

## Collection declarations

Use `list<T>` for JSON arrays. Lists may be empty and preserve the `[]` shape.

Use `non-empty-list<T>` when the JSON array must contain at least one item.
It supports the same item types as `list<T>` and rejects an empty JSON array.

Use `non-empty-array<string, T>` for a JSON object that must contain at least
one member. The string-key and nonempty constraints ensure that PHP's
`json_encode()` represents every supported value as an object. Decoding rejects
JSON arrays and empty JSON objects for these fields.

Use `ArrayObject<string, T>` for a JSON object that may be empty. `ArrayObject`
is a standard PHP container whose empty value encodes as `{}`. The decoder
treats it as a map container rather than as an ordinary non-final class and
always constructs an exact `ArrayObject`; subclasses are outside the supported
round-trip domain.

```php
<?php

declare(strict_types=1);

use ArrayObject;

final readonly class Catalog
{
    /**
     * @param non-empty-array<string, Product> $featured
     * @param ArrayObject<string, Product> $products
     */
    public function __construct(
        public array $featured,
        public ArrayObject $products,
    ) {}
}
```

The currently supported `T` declarations are `string`, `int`, `float`, `bool`,
a backed enum, or a final class. Whole-valued integers are restored as floats
when `T` is `float`. The same declarations work on public properties outside the
constructor.

Collection items and values may also use `non-empty-string` or `numeric-string`,
such as `list<non-empty-string>` or `ArrayObject<string, numeric-string>`.
Both declarations validate values as strings without coercion and preserve the
collection's shape rules. The narrower nonempty and numeric constraints are not
validated at runtime. Errors report the underlying `string` type.

Integer-range items and values, such as `list<int<5, max>>`,
`non-empty-array<string, int<min, -1>>`, and `ArrayObject<string, int<-5, 5>>`,
are also recognized. Bounds may be decimal integers, `min` for the lower bound,
or `max` for the upper bound. These declarations validate values as integers
without coercion and preserve collection shape rules. Range bounds are not
enforced at runtime. Errors report the underlying `int` type.

The integer refinements `positive-int`, `negative-int`, `non-negative-int`, and
`non-positive-int` are recognized in collection items and values as well. They
validate the underlying `int` type without coercion and retain the same
collection shape rules. Their sign constraints are not enforced at runtime.
These names follow [PHPStan's integer range declarations](https://phpstan.org/writing-php-code/phpdoc-types#integer-ranges).

Map member names must remain PHP string keys. Integer names such as `"0"`,
`"-1"`, and `"42"` are rejected because PHP converts them to integer array keys,
even when the keys are non-sequential. Numeric-looking names that remain
strings, such as `"01"`, `"+1"`, `"1.0"`, and `"1e0"`, are supported and preserved.
This rule applies to both map forms in constructor fields and public properties.
Imported aliases and more complex value declarations are not yet resolved or
validated.
Plain `array<TKey, TValue>` declarations are rejected with a `DecodeError`;
choose one of the two map declarations above to state whether the map may be
empty.

## Supported types and limits

The table describes the behavior that is currently covered by the acceptance
suite. "Not yet supported" means that the behavior remains on the roadmap and
must not be relied on, even if a particular value happens to decode.

| Target or value | Status | Limits |
| --- | --- | --- |
| Concrete classes | Supported at the root | The JSON root must be an object. Recognized member names are passed as named constructor arguments, so they must match the constructor parameter names. Each parameter must have a same-named public instance property so `json_encode()` can expose its value. Additional members are ignored. |
| Readonly classes | Supported at the root | The same constructor rules as other concrete classes apply. |
| `string`, `int`, `float`, and `bool` constructor fields | Supported | Values must have the declared type. An integer JSON value is also valid for a `float` field because the declaration restores it as a PHP float. Other implicit scalar coercions are rejected. |
| `null`, nullable scalar fields, and literal `true` and `false` fields | Supported | A non-null value must still match the non-null member of a nullable type. Literal Boolean fields accept only their declared value. |
| Collection fields | Limited | PHPDoc `list<T>` and `non-empty-list<T>` declarations are decoded and validated for constructor fields and public properties, where `T` is `string`, `int`, `float`, `bool`, a backed enum, or a final class. Empty JSON arrays are rejected for `non-empty-list<T>`. Nonempty maps use `non-empty-array<string, T>`; maps that may be empty use `ArrayObject<string, T>`. For either map form, `T` supports the same declarations. JSON arrays are rejected for maps, `{}` is rejected for nonempty-array maps, and member names that PHP converts to integer keys are rejected. Whole-valued JSON integers in float collections are restored as floats. Class and enum names may be fully qualified or in the declaring class's named namespace; imported aliases and global-namespace shorthand are not yet resolved. Other PHPDoc item and value types are not yet read or validated. Native arrays without a collection PHPDoc declaration are passed through. |
| Nested class fields | Supported for final classes | JSON objects are recursively converted to final classes declared directly on constructor fields, including readonly classes, nullable fields, `self` declarations that resolve to a final class, and unions with backed enums, scalar, and null members. Non-final declarations, including `parent`, are rejected because their values may be subclasses. Class members of unions that also contain lists and maps are not yet supported. |
| Backed enum fields | Supported in direct constructor fields, public properties, unambiguous unions, lists, and maps | String-backed and int-backed values are converted without coercion, including nullable enum fields, lists, `non-empty-array<string, BackedEnum>` and `ArrayObject<string, BackedEnum>` maps, unions where scalar members use different JSON types from the enum backing type, and unions of backed enums whose case values do not overlap. Non-backed enums are rejected because they have no JSON representation. |
| General union types | Limited | Nullable scalar declarations, one final class alongside a backed enum and scalar or null members, backed enum/scalar unions with distinct JSON types, and unambiguous unions of backed enums are supported. Scalar members must use JSON types distinct from the enum's backing type. Other unions do not yet have a supported selection policy. |
| Intersection types | Rejected | JSON does not identify a concrete class that satisfies the intersection. |
| Interfaces and abstract classes | Rejected at the root and in field types | Root targets, direct constructor field declarations, union members, list item declarations, and map value declarations return dedicated errors because JSON does not identify a concrete implementation or subclass to instantiate. |
| Public properties outside the constructor, inherited properties, and other unsupported class shapes | Limited | Declared scalar, array, backed enum, nested final class, and supported union public properties are hydrated after construction, including inherited properties, nullable and recursively nested `self` declarations, and classes without constructors. Public-property unions follow the same class, enum, scalar, null, and ambiguity rules as constructor fields. Array properties use the same limited normalization as constructor array fields. Constructor-bound properties, including promoted readonly properties, are not assigned again. Omitted public properties keep their state after construction: initialized properties retain their values, while uninitialized properties remain uninitialized, including readonly properties. Targets with private or protected constructors are rejected with a dedicated error. |
| `mixed`, untyped fields, `object`, and `stdClass` | Rejected | These declarations cannot preserve every value's original PHP type and JSON object/array shape, so they return a dedicated `DecodeError` whether or not the member is present. Use `ArrayObject<string, T>` rather than `stdClass` for a typed JSON object map. |
| Root arrays, maps, scalars, enums, and `null` | Not yet supported | `Json::decode()` currently accepts only a JSON object and a class target. |
| Classes implementing `JsonSerializable` | Rejected | A custom JSON representation may not correspond to constructor parameters, so these targets return a dedicated `DecodeError`. |

The round-trip contract also has these representation limits:

- The source object must be encodable by PHP's `json_encode()`. Resources,
  non-finite floats, invalid UTF-8, and circular references are outside the
  supported input domain.
- JSON does not retain PHP class identity. It cannot choose between multiple
  possible classes without an additional discriminator or selection rule.
- A backed enum case and its backing scalar encode to the same JSON value, so a
  union containing both cannot recover the original PHP type.
- Native arrays without a supported collection declaration are normalized from
  decoded `stdClass` objects to PHP arrays. Within those values, empty objects
  and empty arrays both become empty PHP arrays, and numeric-looking object keys
  may become integer array keys. Supported typed maps avoid that ambiguity by
  requiring either `non-empty-array<string, T>` or `ArrayObject<string, T>`.
- JSON object members without matching constructor parameters or supported
  public properties are ignored and do not appear when the decoded object is
  re-encoded.
- A missing required constructor-bound member produces a construction error. A
  missing optional member uses its constructor default. A present `null` is
  passed to the constructor and therefore overrides a nullable non-null default.
  An omitted public property outside the constructor keeps its initialized or
  uninitialized state. Re-encoding may include a default-valued public property
  whose member was absent from the input. Exact preservation of omissions is
  outside the object-to-JSON-to-object round-trip contract.

## TODO

### Scalar fields and validation

- [x] Test all scalar field types: `string`, `int`, `float`, and `bool`, plus `null`, nullable types, and literal `true`/`false` types.
- [x] Reject field values that do not match the declared type, including values that reflection would otherwise silently coerce.
- [x] Test numeric boundaries and define how to preserve whole-valued floats, large integers, and precision during round trips.
- [x] Test strings containing Unicode, escaped characters, empty strings, and numeric-looking text.
- [ ] Test rejection of non-JSON-encodable object field values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references. Define the validation boundary; the current API uses PHP's `json_encode()` for encoding.

### Objects and construction

- [x] Recursively decode nested JSON objects into their declared final classes, including readonly classes and nullable object fields.
- [x] Hydrate declared scalar public properties outside the constructor, including classes without constructors and inherited properties. Hydrate each JSON member once so promoted properties, including readonly properties, are not assigned a second time.
- [x] Hydrate declared array public properties outside the constructor using the same limited normalization as constructor array fields.
- [x] Extend public-property hydration to backed enums, including nullable fields and strict backing-value validation.
- [x] Extend public-property hydration to nested final classes.
- [x] Extend public-property hydration to supported unions.
- [x] Define and test how omitted constructor-bound members interact with required and optional arguments, constructor defaults, promoted readonly properties, and explicit `null`. Missing optional members use their defaults, present `null` overrides nullable defaults, and re-encoding may include default-valued properties that were absent from the input.
- [x] Decide and test how omitted JSON object members interact with initialized and uninitialized public properties outside the constructor, including readonly properties.
- [x] Test that JSON member order does not affect constructor argument binding.
- [ ] Preserve the distinction between JSON objects and arrays, especially `{}` versus `[]` and objects with numeric-looking keys.
- [x] Ignore unknown JSON fields at the root and in nested objects.
- [x] Define the policy for duplicate JSON member names.
- [x] Reject interface and abstract class root targets with a clear `DecodeError`.
- [x] Reject direct interface and abstract class field types with a clear `DecodeError`.
- [x] Reject interface and abstract class union members with a clear `DecodeError`.
- [x] Reject non-final class field types because JSON cannot preserve a value's runtime subclass.
- [x] Reject interface and abstract class list item types with a clear `DecodeError`.
- [x] Reject interface and abstract class map value types with a clear `DecodeError`.
- [x] Reject target classes with private or protected constructors with a clear `DecodeError`.
- [x] Reject constructor parameters without same-named declared public instance properties because the class shape provides no stable JSON member from which to recover their values.
- [ ] Define the remaining supported class shapes and report unsupported ones clearly.
- [x] Define behavior for `mixed`, untyped fields, `object`, and `stdClass`.
- [ ] Investigate parsing JSON directly into target objects instead of decoding to generic PHP values and then mapping those values onto objects.
- [x] Support unions that combine one concrete class and one backed enum, alongside scalar and null members whose types do not overlap the enum's backing type, such as `MyEnum|MyClass|null`.
- [x] Reject unions containing multiple classes (such as `ClassA|ClassB`).
- [x] Select the single class member in supported unions by JSON value kind without discriminators or inspecting class fields.
- [x] Reject unions containing multiple enums.
- [x] Support unions containing multiple backed enums when each JSON scalar representation identifies exactly one enum case; continue rejecting declarations with overlapping backing values.
- [x] Initially reject union declarations combining a backed enum with its backing scalar type, such as `MyStringBackedEnum|string` and `MyIntBackedEnum|int`, with a clear `DecodeError`. Apply this rule to larger and nullable unions and reject the declaration regardless of the input value.
- [x] Test supported enum/scalar unions with distinct JSON types, such as `MyStringBackedEnum|int` and `MyIntBackedEnum|string`, without coercion.
- [x] Define support or explicit rejection for intersection types.
- [x] Define the round-trip contract for classes implementing `JsonSerializable`, whose JSON representation may differ from their properties.

### Enums

- [x] Support string-backed and int-backed enums in direct object fields, including nullable fields.
- [x] Extend unknown-value and backing-type validation to enum members in unions and collections without coercion.
- [x] Reject non-backed enums with a clear error.
- [x] Test enums nested inside objects.
- [x] Test enums nested inside lists.
- [x] Test enums nested inside maps.

### Lists, maps, and root values

- [x] Decode JSON array fields declared as PHPDoc `list<T>` for `string`, `int`, `float`, and `bool` items.
- [x] Extend PHPDoc `list<T>` decoding to backed-enum items.
- [x] Extend PHPDoc `list<T>` decoding to final-class items.
- [ ] Decode JSON array fields into fixed-shape PHPDoc tuples such as `array{int, string}`, validating each position independently.
- [x] Decode nonempty JSON object fields into PHPDoc `non-empty-array<string, T>` with scalar or backed-enum values, validating each value independently.
- [x] Extend typed map decoding to final-class values and `ArrayObject<string, T>` maps that preserve empty JSON objects.
- [ ] Support unions with array-shaped members, such as `list<Foo>|Foo|null`.
- [ ] Support unions inside collection declarations, such as `list<string|int|Foo>`.
- [ ] Support recursively nested lists and maps, including nullable items and values and unions that follow the same rules as object fields.
- [ ] Support arbitrary nesting of different array shapes, such as `list<array<string, list<Foo>>>`.
- [ ] Validate collection items against their declared types and reject object/array shape mismatches.
- [x] Recognize and enforce `non-empty-list<T>` declarations.
- [x] Recognize `non-empty-string` and `numeric-string` collection items and values, validating their underlying string type while preserving collection shape rules. Runtime enforcement of the narrower constraints is optional.
- [x] Recognize integer-range collection items and values such as `int<5, max>`, validating their underlying integer type while preserving collection shape rules. Runtime enforcement of range bounds is optional.
- [x] Recognize `positive-int`, `negative-int`, `non-negative-int`, and `non-positive-int` collection items and values, validating their underlying integer type while preserving collection shape rules. Runtime enforcement of sign constraints is optional.
- [ ] Recognize other narrower PHPDoc types such as `non-zero-int` and `non-empty-map<TKey, TValue>` without rejecting otherwise supported declarations. Runtime enforcement of the narrower constraints is optional.
- [x] Define and test empty-map behavior: reject `{}` for `non-empty-array<string, T>` and preserve it with `ArrayObject<string, T>`.
- [x] Test map keys, including integer keys, numeric-looking string keys, and non-sequential keys; preserve JSON shape during round trips.
- [ ] Resolve PHPDoc collection types using the declaring namespace and imported or aliased class names.
- [ ] Report missing, malformed, unknown, or unsupported collection type declarations clearly.
- [ ] Add an API for typed JSON arrays at the root, with accurate generic return types for static analysis.
- [ ] Support typed maps at the root and define whether scalar, enum, and null root values are supported.

### Errors and acceptance coverage

- [x] Keep every decoding failure on the returned `DecodeError` path, including nested conversion failures and exceptions thrown by constructors.
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
