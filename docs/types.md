# Type reference

[Back to the README](../README.md)

Details on supported declarations, construction, and JSON round trips.

## Supported types and limits

The table describes supported behavior and its limits. Unsupported behavior
must not be relied on, even if a particular value happens to decode.

| Target or value | Status | Limits |
| --- | --- | --- |
| Concrete classes | Supported at the root | The JSON root must be an object. Recognized member names are passed as named constructor arguments, so they must match the constructor parameter names. Each parameter must have a same-named public instance property so `json_encode()` can expose its value. Additional members are ignored. |
| Readonly classes | Supported at the root | The same constructor rules as other concrete classes apply. |
| `string`, `int`, `float`, and `bool` constructor fields | Supported | Values must have the declared type. An integer JSON value is also valid for a `float` field because the declaration restores it as a PHP float. Other implicit scalar coercions are rejected. |
| `null`, nullable scalar fields, and literal `true` and `false` fields | Supported | A non-null value must still match the non-null member of a nullable type. Literal Boolean fields accept only their declared value. |
| Collection fields | Limited | PHPDoc `list<T>` and `non-empty-list<T>` declarations are decoded and validated for constructor fields and public properties, where `T` is `string`, `int`, `float`, `bool`, a backed enum, a final class, or a supported union of those types and `null`, `true`, or `false`. Lists, tuples, and maps may nest recursively in any combination, including nullable nested collections and supported unions, with the same rules at every level. Empty JSON arrays are rejected for `non-empty-list<T>`. Positional tuples such as `array{int, string}` require exactly the declared positions and validate each independently, using the same supported item types. `array{}` preserves empty JSON arrays. Explicit consecutive indexes starting at zero and optional trailing positions are supported, such as `array{0: int, 1?: string}`; omitted positions remain absent. Nonempty maps use `non-empty-array<string, T>` or its alias `non-empty-map<string, T>`; maps that may be empty use `ArrayObject<string, T>`. For either map form, `T` supports the same declarations. JSON arrays are rejected for maps, `{}` is rejected for nonempty-array maps, and member names that PHP converts to integer keys are rejected. Whole-valued JSON integers in float collections are restored as floats. Class and enum names follow the declaring class's namespace and class imports, including aliases, grouped imports, namespace aliases, fully qualified names, namespace-relative names, and global-namespace shorthand. `self` resolves to the declaring class and requires it to be final. Missing, malformed, unknown, and unsupported collection declarations return a `DecodeError`, including when the member is omitted. Native arrays require a supported collection PHPDoc declaration. |
| Nested class fields | Supported for final classes | JSON objects are recursively converted to final classes declared directly on constructor fields, including readonly classes, nullable fields, `self` declarations that resolve to a final class, and unions with backed enums, scalar, and null members. Non-final declarations, including `parent`, are rejected because their values may be subclasses. A final class may join a union with a list or tuple; maps and classes cannot share a union because both encode as JSON objects. |
| Backed enum fields | Supported in direct constructor fields, public properties, unambiguous unions, lists, and maps | String-backed and int-backed values are converted without coercion, including nullable enum fields, lists, `non-empty-array<string, BackedEnum>` and `ArrayObject<string, BackedEnum>` maps, unions where scalar members use different JSON types from the enum backing type, and unions of backed enums whose case values do not overlap. Non-backed enum values cannot be decoded. A non-backed enum without custom serialization may join a union with supported members, including `null`. |
| General union types | Limited | Nullable scalar declarations, one final class alongside a backed enum and scalar or null members, backed enum/scalar unions with distinct JSON types, and unambiguous unions of backed enums are supported. Scalar members must use JSON types distinct from the enum's backing type. Supported collection declarations may join field, tuple-position, list-item, and map-value unions, with at most one member for each JSON array or object shape. |
| Intersection types | Rejected | JSON does not identify a concrete class that satisfies the intersection. |
| Interfaces and abstract classes | Rejected at the root and in field types | Root targets, direct constructor field declarations, union members, list item declarations, and map value declarations return dedicated errors because JSON does not identify a concrete implementation or subclass to instantiate. |
| Public properties outside the constructor, inherited properties, and other unsupported class shapes | Limited | Declared scalar, array, backed enum, nested final class, and supported union public properties are hydrated after construction, including inherited properties, nullable and recursively nested `self` declarations, and classes without constructors. Public-property unions follow the same class, enum, scalar, null, and ambiguity rules as constructor fields. Array properties require the same supported collection declarations as constructor fields. Collection unions require PHPDoc matching the native union members and follow the same JSON-shape selection rules. Constructor-bound properties, including promoted readonly properties, are not assigned again. Omitted public properties keep their state after construction: initialized properties retain their values, while uninitialized properties remain uninitialized, including readonly properties. Targets with private or protected constructors are rejected with a dedicated error. |
| `mixed`, untyped fields, `object`, and `stdClass` | Rejected | These declarations cannot preserve every value's original PHP type and JSON object/array shape, so they return a dedicated `DecodeError` whether or not the member is present. Use `ArrayObject<string, T>` rather than `stdClass` for a typed JSON object map. |
| Root arrays and maps | Limited | `JsonType::array()` returns lists and `JsonType::map()` returns `ArrayObject<string, T>` maps, preserving empty JSON arrays and objects. Values may be final classes, backed enums, or recursively nested array and map descriptors. Map keys must remain PHP strings. |
| Root scalars, enums, and `null` | Unsupported | There are no descriptors for standalone values of these types. Use a transport class with a typed field. |
| Classes implementing `JsonSerializable` | Rejected | A custom JSON representation may not correspond to constructor parameters, so these targets return a dedicated `DecodeError`. |

The round-trip contract also has these representation limits:

- The source object must be encodable by PHP's `json_encode()`. Resources,
  non-finite floats, invalid UTF-8, and circular references are outside the
  supported input domain.
- JSON does not retain PHP class identity. It cannot choose between multiple
  possible classes without an additional discriminator or selection rule.
- A backed enum case and its backing scalar encode to the same JSON value, so a
  union containing both cannot recover the original PHP type.
- Collection declarations enforce both item types and JSON shape. Lists require
  JSON arrays; maps require JSON objects. Scalar and enum items are validated
  without coercion, except that integer JSON values are restored as floats when
  declared as `float`. Final-class items require JSON objects and are validated
  recursively. The same rules apply to constructor fields and public properties.
  Native arrays without a supported collection declaration are rejected.
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

## Numeric round trips

Integer fields support values from `PHP_INT_MIN` through `PHP_INT_MAX`. PHP
decodes JSON integers outside that platform range as floats, so they are
rejected instead of being rounded or coerced back to integers.

Float fields use PHP's native floating-point precision. The default
`json_encode()` and `json_decode()` behavior preserves finite values, including
subnormal values and precision-sensitive decimals. JSON does not distinguish a
whole-valued float such as `3.0` from the integer `3`; the declared field type
restores the value to a PHP float during construction.

## JSON objects and arrays

Decoding keeps JSON objects and arrays distinct, including empty values. An
empty concrete object round-trips as `{}`, an empty `list<T>` as `[]`, and an
empty `ArrayObject<string, T>` as `{}`. This also applies to empty concrete
objects inside lists and maps. A JSON array cannot stand in for a concrete
object or map, and a JSON object cannot stand in for a list, even when its
member names are sequential integers such as `"0"` and `"1"`.

Maps preserve numeric-looking member names that remain PHP string keys, such
as `"01"` and `"1e0"`. They reject names that PHP converts to integer keys, such
as `"0"` and `"42"`. For concrete class targets, numeric-looking names are
unknown fields and follow the same ignore policy as other unknown members.
These rules also apply to root collections declared with `JsonType::array()`
or `JsonType::map()`.

## Collection declarations

Use `list<T>` for JSON arrays. Lists may be empty and preserve the `[]` shape.

Use `non-empty-list<T>` when the JSON array must contain at least one item.
It supports the same item types as `list<T>` and rejects an empty JSON array.

Use a positional tuple such as `array{int, string}` for a JSON array with a
fixed number of items. Each position has its own type and all positions are
required. The decoder rejects missing or extra items, JSON objects, and values
that do not match their position's type. `array{}` accepts only `[]`.
Tuple positions support the same scalar, backed-enum, final-class, and nested
collection types as list items, including the recognized string and integer
refinements below.
Explicit numeric positions are also supported, such as
`array{0: Person, 1: string, 2?: int}`. Positions must be consecutive and start
at zero. Optional positions must follow all required positions; values may
omit only a trailing suffix. The example accepts two or three items. An
all-optional tuple such as `array{0?: int, 1?: string}` also accepts `[]`.
Omitted positions stay absent, while a present `null` must match the declared
item type and is rejected for these non-nullable types.

Declarations with gaps, duplicate or out-of-order indexes, or required
positions after optional ones return a `DecodeError`, even when the field is
omitted. JSON objects with numeric member names cannot stand in for tuples.
These rules preserve JSON array shape. Named keys and open shapes are not yet supported.
Tuple positions may use the supported unions described below.

Declare tuples with `@param` on constructor fields or `@var` on public
properties, just like other collections:

```php
final readonly class Label
{
    /** @param array{int, string} $value */
    public function __construct(public array $value) {}
}
```

Decoding `{"value":[42,"answer"]}` into `Label::class` restores the tuple.
`{"value":["42","answer"]}`, `{"value":[42]}`, and
`{"value":[42,"answer",true]}` each return a `DecodeError`. Value errors name
the failing position, such as `value[0]`; length errors report the expected
count or allowed range and the actual number of items.

Use `non-empty-array<string, T>` for a JSON object that must contain at least
one member. The string-key and nonempty constraints ensure that PHP's
`json_encode()` represents every supported value as an object. Decoding rejects
JSON arrays and empty JSON objects for these fields.

`non-empty-map<string, T>` is an alias for `non-empty-array<string, T>`.
It supports the same values, including supported unions, and uses the same
string-key and nonempty validation for constructor fields and public properties.
Runtime errors use the normalized `non-empty-array` name. Other key types
remain unsupported.

Use `ArrayObject<string, T>` for a JSON object that may be empty. `ArrayObject`
is a standard PHP container whose empty value encodes as `{}`. The decoder
treats it as a map container rather than as an ordinary non-final class and
always constructs an exact `ArrayObject`; subclasses are outside the supported
round-trip domain.

Declare constructor collections with `@param` on the constructor. This complete
example uses all four collection forms and restores map values as `Product`
objects:

```php
<?php

declare(strict_types=1);

namespace Example;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;

final readonly class Product
{
    public function __construct(public string $name) {}
}

final readonly class Catalog
{
    /**
     * @param list<string> $tags
     * @param non-empty-list<int> $quantities
     * @param non-empty-array<string, Product> $featured
     * @param ArrayObject<string, Product> $products
     */
    public function __construct(
        public array $tags,
        public array $quantities,
        public array $featured,
        public ArrayObject $products,
    ) {}
}

$catalog = Json::decode(
    '{"tags":[],"quantities":[1,2],"featured":{"primary":{"name":"Notebook"}},"products":{}}',
    Catalog::class,
);

if ($catalog instanceof DecodeError) {
    throw $catalog;
}

echo $catalog->featured['primary']->name;
```

The example prints `Notebook`. Re-encoding `$catalog` preserves `tags` as `[]`
and `products` as `{}`. Changing `quantities` to `[]`, `featured` to `{}`, or
either map to a JSON array returns a `DecodeError`.

For public properties outside the constructor, put `@var` on the property:

```php
final class Labels
{
    /** @var list<string> */
    public array $values = [];
}
```

Decoding `{"values":["new","sale"]}` into `Labels::class` hydrates the list.
An omitted `values` member leaves its default `[]` unchanged. An item of the
wrong type, such as `{"values":[42]}`, returns a `DecodeError`.

Every array or `ArrayObject` field needs a supported declaration: `@param` on
the constructor for a constructor-bound field, or `@var` on a public property
outside the constructor. Missing or unrecognized declarations return a
`DecodeError` naming the field and the supported collection forms, even when
the JSON member is omitted. The decoder does not fall back to an unvalidated
array. This includes malformed PHPDoc, unknown type names, and types whose
conversion is not supported, such as named or open array shapes and unions
that combine multiple members with the same JSON shape. Existing dedicated errors for ambiguous map declarations
and unsupported class types still apply.

Collection items and values may use unions, such as `list<string|int|Person>`,
`ArrayObject<string, Status|null>`, or `array{int|string, Person|null}`.
Each union may contain scalar types, `true`, `false`, `null`, one final class,
and backed enums with unambiguous case values. The same class and enum
restrictions as ordinary object fields apply, even when the collection is
empty or the field is omitted. Enum backing scalars and overlapping enum
values remain ambiguous and are rejected. Union order does not affect selection.
For scalar-only unions, an integer remains an integer when `int` is present;
a `float` member restores whole-valued floats when `int` is absent. Explicit `null` is accepted only when
`null` is a member. Optional tuple positions can still be omitted independently.

A union may also contain types whose values cannot be encoded: non-backed
enums without custom serialization, and PHPDoc `resource`, `open-resource`,
or `closed-resource` members. At least one member must be supported for
decoding. For example, `NonBackedEnum|string` decodes strings,
`?NonBackedEnum` accepts only `null`, and `list<resource|int>` decodes integer
items. Declarations containing only non-encodable alternatives are rejected,
even when the field is omitted or its collection is empty. The existing
ambiguity rules still apply to the supported members. Encodable but unsupported
types, such as interfaces, abstract classes, and enums implementing
`JsonSerializable`, do not qualify for this exception. Encoding remains the
caller's `json_encode()` responsibility; `float` remains supported even though
`INF` and `NAN` cannot be encoded.

Lists, tuples, and maps may nest recursively in any combination, using the
same supported item and value types at every level. For example, `list<list<int|null>>`,
`non-empty-array<string, list<Person>>`, and
`ArrayObject<string, ArrayObject<string, Status|null>>` are supported.
Each level retains its shape rules: lists encode as arrays, nonempty maps
encode as objects and reject `{}`, and `ArrayObject` maps preserve `{}`.
A nested collection may also be nullable, such as `list<list<int>|null>`;
`null` and an empty list remain distinct. These declarations work on constructor
fields and public properties, and errors include the complete path through
all collection levels. Class and enum restrictions are validated even inside
empty collections or when the field is omitted.

Collections may join unions with scalar, enum, class, and null members.
This works for constructor fields, public properties, tuple positions, list
items, and map values. Selection uses JSON shape: an array selects a list or tuple, an
object selects a map or final class, and a scalar follows the existing union
rules. For example, `list<Person>|Person|null` restores either a list of
people, one person, or `null`. Nested declarations such as
`list<list<Person>|Person|null>` use the same selection rules.

For a field union, PHPDoc must describe every native union member, replacing
`array` and `ArrayObject` with their supported collection declarations:

```php
final readonly class People
{
    /** @param list<Person>|Person|null $value */
    public function __construct(public array|Person|null $value) {}
}
```

Use `@var` in the same way on a public property outside the constructor.
Nullable collection fields, such as native `?array` with PHPDoc
`list<int>|null`, are supported. PHPDoc member order does not affect selection,
and repeated members with the same normalized declaration are ignored.
PHPDoc cannot add or omit a
native union member. Existing scalar refinements normalize to their underlying
type; they do not add runtime constraints.

Each union may have at most one JSON-array member and one JSON-object member.
`list<int>|ArrayObject<string, int>` preserves both `[]` and `{}`.
`list<int>|non-empty-array<string, int>` also works, with an empty object
rejected by the map's nonempty constraint. `list<int>|list<string>` and
`list<int>|array{int}` are rejected because JSON arrays do not identify which
member to restore. A map alongside a class or another map is rejected for the
same reason. Unsupported or ambiguous declarations are rejected even when
the collection is empty or the field is omitted.

Tuples may contain lists, maps, and other tuples, and may appear inside list
items, map values, or supported unions. For example,
`list<array{Person, ArrayObject<string, list<int>>}>` describes a list of
pairs: each pair contains a person and a map of integer lists.
`array{0: list<Person>, 1?: ArrayObject<string, array{int, string}>}`
also supports an omitted trailing map position. A present empty map remains
`{}`, an empty list remains `[]`, and omitted tuple positions stay absent.
Nullable collection positions distinguish `null` from an omitted position.
The existing 64-type-level declaration limit applies to mixed nesting too.

Generic `array<string, T>` maps remain unsupported at every level because
their empty value cannot preserve JSON object shape.

Collection types may span multiple PHPDoc lines, including lines with the
usual leading `*` and either LF or CRLF line endings:

```php
/**
 * @param array{
 *     0: int,
 *     1?: string
 * } $value
 */
```

The internal parser builds a syntax tree for nested list, map, and tuple
syntax, including unions and intersections, without runtime dependencies. Parsing a nested declaration does not
enable its decoding: the supported collection forms and item types above
still apply. Declarations are limited to 64 type levels, counting the outer
collection and its innermost item, to bound parser recursion.

These PHPDoc declarations describe fields inside a root object. For root
collections, use `JsonType::array()` or `JsonType::map()`; PHPDoc strings are
not accepted as targets.

The currently supported `T` declarations are `string`, `int`, `float`, `bool`,
a backed enum, a final class, or a supported union as described above.
Whole-valued integers are restored as floats
when `T` is `float`. The same declarations work on public properties outside the
constructor. Collection items and values may use `self` to refer to the class
that declares the field, such as `list<self>` or `ArrayObject<string, self>`.
That class must be final, including when the declaration is inherited.

Collection items and values may also use `non-empty-string`, `numeric-string`,
or `literal-string`, such as `list<non-empty-string>` or `ArrayObject<string, numeric-string>`.
These declarations validate values as strings without coercion and preserve the
collection's shape rules. The narrower nonempty, numeric, and literal-string
constraints are not validated at runtime. Errors report the underlying `string`
type.

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

`non-zero-int` is also recognized as `int` in list items, tuple positions,
map values, and supported unions. It validates the integer type without
coercion; the nonzero constraint is not enforced, so zero is accepted.
Errors report the underlying `int` type.

Map member names must remain PHP string keys. Integer names such as `"0"`,
`"-1"`, and `"42"` are rejected because PHP converts them to integer array keys,
even when the keys are non-sequential. Numeric-looking names that remain
strings, such as `"01"`, `"+1"`, `"1.0"`, and `"1e0"`, are supported and preserved.
This rule applies to both map forms in constructor fields and public properties.

Class and enum names in collection PHPDoc follow the namespace and class
imports of the class that declares the field. This includes ordinary `use`
imports, aliases, grouped imports, namespace aliases such as `Models\Person`,
fully qualified names such as `\Example\Person`, and namespace-relative names
such as `namespace\Person`. Names in a global-namespace class resolve directly
in the global namespace. Aliases are case-insensitive. Function and constant
imports do not supply class names, and unknown names still return a
`DecodeError`, including when the field is omitted. Inherited fields use their
declaring class's imports. An alias for the `ArrayObject` container itself is
also supported.

Import resolution reads the declaring class's PHP source file. Keep that source
available and consistent with the loaded class when using imported PHPDoc names.
Fully qualified names do not need source-file access.

Plain `array<TKey, TValue>` declarations are rejected with a `DecodeError`;
choose one of the two map declarations above to state whether the map may be
empty.

## Explicitly unsupported

These exclusions keep decoding unambiguous and preserve PHP types and JSON shapes.

| Feature or value | Why it is unsupported |
| --- | --- |
| Unions containing more than one class, such as `ClassA\|ClassB` | JSON objects do not identify their PHP class. Choosing a class would require a discriminator or inspecting class fields, adding complexity to decoding. |
| Unions containing multiple enums with overlapping backing values | The same JSON scalar would identify cases from more than one enum, so decoding could not recover the original enum type. |
| Unions combining a backed enum with its backing scalar type, such as `MyStringBackedEnum\|string` or `MyIntBackedEnum\|int` | An enum case and its backing scalar encode to the same JSON value. Checking the enum first would turn an original scalar into an enum case; checking the scalar first would lose the enum case. JSON cannot recover the original PHP type, so these unions break the round-trip contract. |
| Interface and abstract class type hints | They cannot be instantiated directly, and JSON does not identify which concrete implementation or subclass to create. Resolving one would require additional selection rules or metadata. |
| Non-final class type hints in object fields | A field may contain a subclass of its declared type, but JSON does not identify that runtime class. Reconstructing the declared class could silently change the value. Root targets are unaffected because the caller supplies their exact class. |
| Intersection type hints | JSON does not identify the concrete class that satisfies every member of an intersection. Resolving one would require additional selection rules or metadata. |
| Non-backed enum values | Without custom serialization, they have no JSON representation. Their type may appear in a union with a supported member, including `null`; only supported members can be decoded. Enums implementing `JsonSerializable` remain unsupported because their custom representation cannot be recovered generically. |
| Object targets implementing `JsonSerializable` | Their custom JSON representation may not match their constructor parameters, so generic decoding cannot guarantee the round-trip contract. |
| Variadic constructors | JSON members bind to individual named constructor arguments. Reconstructing a variadic argument list would require separate unpacking and key-binding rules. |
| Constructor parameters without same-named declared public instance properties | The class shape does not provide a stable JSON member from which decoding can recover those argument values. |
| Non-JSON-encodable values, including resources, `INF`, `NAN`, invalid UTF-8, and circular references | JSON cannot represent these values without losing information or changing their meaning. They fall outside the round-trip contract. |
| JSON objects with duplicate member names | They cannot be produced by `json_encode()` from supported PHP values. PHP's decoder does not report them separately, and detecting them would require reparsing every document. Callers must not rely on which duplicate value is retained. |
| Implicit coercion of mismatched scalar types | Converting values such as `"42"` to `42` hides a type mismatch and changes the value's type. Decoding must validate the declared type. |
| Member-name mapping, aliases, and naming strategies | Target classes are expected to match the JSON wire format exactly. Transforming decoded transport data into an application's domain model is the consumer's responsibility. |
| `mixed`, untyped, `object`, and `stdClass` constructor fields | These declarations do not provide enough type information to restore every value's original PHP type and JSON shape. In particular, `object` omits the concrete class, while `mixed`, untyped fields, and `stdClass` members can contain PHP arrays that encode as JSON objects. |
| Generic `array<TKey, TValue>` map declarations | An empty PHP array encodes as `[]`, so it cannot represent an empty JSON object. Depending on its runtime keys, a nonempty PHP array can also encode as either an array or an object. Use `non-empty-array<string, TValue>` for a map that cannot be empty or `ArrayObject<string, TValue>` for a map that may be empty. |
