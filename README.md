# eventjet/json

Type-safe JSON decoding for PHP.

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
| Non-backed enums | They have no scalar backing value and cannot round-trip through PHP's `json_encode()`. |
| Non-JSON-encodable values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references | JSON cannot represent these values without losing information or changing their meaning. They fall outside the round-trip contract. |
| Implicit coercion of mismatched scalar types | Converting values such as `"42"` to `42` hides a type mismatch and changes the value's type. Decoding must validate the declared type. |

Open decisions, including intersection types, remain in the TODO list until
their scope is settled.

## TODO

### Scalar fields and validation

- [ ] Test all scalar field types: `string`, `int`, `float`, and `bool`, plus `null`, nullable types, and literal `true`/`false` types.
- [ ] Reject field values that do not match the declared type, including values that reflection would otherwise silently coerce.
- [ ] Test numeric boundaries and define how to preserve whole-valued floats, large integers, and precision during round trips.
- [x] Test strings containing Unicode, escaped characters, empty strings, and numeric-looking text.
- [ ] Test rejection of non-JSON-encodable object field values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references. Define the validation boundary; the current API uses PHP's `json_encode()` for encoding.

### Objects and construction

- [ ] Decode nested JSON objects into their declared classes, including readonly classes and nullable object fields.
- [ ] Support public properties that are not promoted constructor parameters, including classes without constructors and inherited properties.
- [ ] Test required constructor arguments, omitted optional arguments, defaults, and explicit `null`; distinguish a missing field from a present null value.
- [ ] Define and test how omitted JSON object members interact with PHP constructor and property defaults. Decide whether re-encoding may include initialized default-valued properties or must preserve the original omissions, and whether this requires additional support beyond round trips that start with a PHP object.
- [ ] Test that JSON member order does not affect constructor argument binding.
- [ ] Preserve the distinction between JSON objects and arrays, especially `{}` versus `[]` and objects with numeric-looking keys.
- [ ] Define and test the policy for unknown JSON fields and duplicate JSON member names.
- [ ] Reject interface and abstract class targets with a clear `DecodeError`, including root targets, field types, union members, and collection item/value types.
- [ ] Define supported class shapes and report unsupported ones clearly, including inaccessible constructors and constructor arguments that cannot be recovered from encoded properties.
- [ ] Define behavior for `mixed`, untyped fields, `object`, and `stdClass`.
- [ ] Support unions with at most one concrete class and one backed enum, alongside scalar and null members whose types do not overlap the enum's backing type, such as `Foo|string|int` and `MyEnum|MyClass|null`.
- [ ] Reject unions containing multiple classes (such as `ClassA|ClassB`) or multiple enums. Select the class member by JSON value kind without discriminators or inspecting class fields.
- [ ] Initially reject union declarations combining a backed enum with its backing scalar type, such as `MyStringBackedEnum|string` and `MyIntBackedEnum|int`, with a clear `DecodeError`. Apply this rule to larger and nullable unions and reject the declaration regardless of the input value.
- [ ] Test supported enum/scalar unions with distinct JSON types, such as `MyStringBackedEnum|int` and `MyIntBackedEnum|string`, without coercion.
- [ ] Define support or explicit rejection for intersection types.
- [ ] Define the round-trip contract for classes implementing `JsonSerializable`, whose JSON representation may differ from their properties.

### Enums

- [ ] Support string-backed and int-backed enums in object fields.
- [ ] Reject unknown enum values and values of the wrong backing type without coercion.
- [ ] Reject non-backed enums with a clear error.
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
- [ ] Expand invalid-JSON coverage to malformed syntax, trailing content, invalid UTF-8, and nesting-depth limits.
- [ ] Test rejection of incompatible root shapes; JSON arrays must not be passed to object constructors as positional arguments.
- [ ] Add acceptance round trips for every supported type and combinations of nested objects, lists, maps, and enums; assert both value types and JSON shape.
- [ ] Keep formatting, dependency checks, static analysis, PHPUnit, and mutation testing passing as support grows.

### Documentation

- [ ] Document installation, object decoding, collection type declarations, root collections, and handling returned errors.
- [ ] Publish the supported-type matrix and explicit limits, including any values whose original PHP type or shape cannot be recovered from JSON alone.
