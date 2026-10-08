# eventjet/json

Decode JSON into typed PHP objects, including nested objects, backed enums, and
collections. Use native type declarations and PHPDoc to describe your data;
`eventjet/json` checks incoming values and constructs the objects for you.

The library pairs with PHP's `json_encode()`: supported PHP values can be
encoded and decoded without losing their types or JSON object/array shapes.
Ordinary data classes need no package-specific attributes or interfaces.
Optional field-name mapping uses `#[Field]` with PHP's `JsonSerializable`.

## Installation

Requires PHP 8.4 or later, with the JSON and Tokenizer extensions. There are no
other runtime dependencies.

```bash
composer require eventjet/json
```

## Quick start

Define a class whose public properties match the JSON member names, then pass
its class name to `Json::decode()`:

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

final readonly class Person
{
    public function __construct(
        public string $name,
        public Status $status,
        public int|null $age = null,
    ) {}
}

$person = Json::decode(
    '{"name":"Ada","status":"active","age":36}',
    Person::class,
);

if ($person instanceof DecodeError) {
    throw $person;
}

echo $person->name;          // Ada
echo $person->status->value; // active

$json = json_encode($person, JSON_THROW_ON_ERROR);
```

PHPStan and Psalm infer `Person|DecodeError` for the result. After the error
check, you can use the object's typed properties directly.

`Json::decode()` returns failures as `DecodeError` values. You choose whether
to throw, log, or otherwise handle them. Errors cover invalid JSON, type
mismatches, unsupported declarations, and construction failures. Conversion
errors include paths such as `people[1].name`; `getPrevious()` retains an
underlying constructor exception when one exists.

## Nested objects and collections

Declare nested objects with their final class type and collection items with
PHPDoc. This example uses the `Person` class above:

```php
final readonly class Team
{
    /** @param list<Person> $members */
    public function __construct(
        public Person $owner,
        public array $members,
    ) {}
}

$team = Json::decode(
    '{"owner":{"name":"Ada","status":"active"},"members":[{"name":"Grace","status":"active"}]}',
    Team::class,
);

if ($team instanceof DecodeError) {
    throw $team;
}

echo $team->members[0]->name; // Grace
```

Put collection declarations in constructor `@param` tags, or in `@var` tags
for public properties outside the constructor.

| JSON value | PHPDoc declaration | PHP value |
| --- | --- | --- |
| Array, possibly empty | `list<T>` | `array` |
| Array with at least one item | `non-empty-list<T>` | `array` |
| Positional array | `array{int, string}` | `array` with typed positions |
| Object, possibly empty | `ArrayObject<string, T>` | `ArrayObject` |
| Object with at least one member | `non-empty-array<string, T>` | `array` with string keys |

Items and values can be scalars, backed enums, final classes, supported unions,
or nested collections. Empty lists stay `[]`; empty `ArrayObject` maps stay
`{}` when encoded. Generic `array<string, T>` maps are unsupported because an
empty PHP array encodes as `[]`.

See the [type reference](docs/types.md#collection-declarations) for nullable
collections, optional tuple positions, unions, and PHPDoc name resolution.

## Root arrays and maps

Use `JsonType::array()` for a JSON array or `JsonType::map()` for a JSON object
whose member names are map keys:

```php
use Eventjet\Json\JsonType;

$people = Json::decode(
    '[{"name":"Ada","status":"active"}]',
    JsonType::array(Person::class),
); // list<Person>|DecodeError

if ($people instanceof DecodeError) {
    throw $people;
}

echo $people[0]->name; // Ada

$directory = Json::decode(
    '{"author":{"name":"Ada","status":"active"}}',
    JsonType::map(Person::class),
); // ArrayObject<string, Person>|DecodeError

if ($directory instanceof DecodeError) {
    throw $directory;
}

echo $directory['author']->name; // Ada
```

Root descriptors accept final classes, backed enums, and nested array or map
descriptors, such as `JsonType::map(JsonType::array(Person::class))`. They do
not accept scalar, nullable, union, or tuple item declarations. Standalone
scalar, enum, and `null` roots are unsupported.

Map keys must remain PHP strings. For example, `"01"` is supported, but `"42"`
is rejected because PHP converts it to an integer key.

## Decoding rules

- JSON member names match constructor parameter or public property names by
  default. Use `#[Field]` for explicit name mapping. Unknown members are ignored.
- Missing required constructor arguments produce an error. Missing optional
  arguments use their defaults. A present `null` requires a nullable type.
  Omitted public properties retain their state after construction.
- Values must match their declared types. For example, `"42"` is rejected for
  an `int` field. Integer JSON values are accepted for `float` fields and
  restored as PHP floats.
- Constructor parameters need same-named public instance properties.
  Nested classes and collection item classes must be final. Interfaces,
  and abstract classes are unsupported. `JsonSerializable` classes require at
  least one `#[Field]` annotation and a matching serializer.
- Unions must identify a type unambiguously. For example, `Person|string|null`
  is supported; `Person|Team` is not, because either class represents a JSON
  object.

Round trips preserve supported values and shapes, not the original JSON text.
Unknown fields are discarded, and re-encoding may include default-valued
properties omitted from the input. PHPDoc refinements such as `positive-int`
and `non-empty-string` validate the underlying scalar type, not the narrower
constraint.

## Mapping JSON member names

Use `#[Field]` when a JSON name differs from its PHP property name, including
keywords such as `$ref`. Any annotated class must implement `JsonSerializable`.
The optional `MappedJsonFields` trait uses the same mappings for native encoding:

```php
use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;

final readonly class Reference implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(#[Field('$ref')] public string $ref) {}
}

$reference = Json::decode('{"$ref":"#/$defs/person"}', Reference::class);
if ($reference instanceof DecodeError) {
    throw $reference;
}
echo json_encode($reference, JSON_THROW_ON_ERROR);
// {"$ref":"#/$defs/person"}
```

Put the attribute on a public instance property, including a promoted property.
Constructor parameters and PHPDoc still use PHP names. A renamed property's PHP
name is not an additional input alias. Duplicate JSON names are rejected.

You may implement `jsonSerialize()` yourself instead of using the trait. Its
output must be an object representation with the declared JSON names and values.
The decoder checks declarations; it cannot prove that arbitrary serializer code
honors them. `JsonSerializable` without any `#[Field]` remains unsupported.

This is an opt-in exception to the library's dependency-free model declarations:
PHP types cannot express these JSON names. Normal classes need neither the
attribute nor the trait. Prefer matching PHP and JSON names when you control the
format: the optimized trait still adds measurable encoding cost, and mapped
constructors do extra work during decoding. Use mapping when the external format
requires it. See [field mapping](docs/types.md#field-name-mapping)
for the full contract and [measurements](docs/performance.md#field-name-mapping)
for performance details.

## Documentation

- [Type reference and limits](docs/types.md)
- [Performance and benchmarks](docs/performance.md)
- [Contributing and local development](CONTRIBUTING.md)

## License

[MIT](LICENSE)
