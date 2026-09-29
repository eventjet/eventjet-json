# eventjet/json
Type-safe JSON parsing and encoding for PHP.

While PHP's native `json_parse()` outputs plain arrays or stdClass objects, this library allows you to decode JSON into pre-defined classes. This allows you to use type-hints and IDE autocompletion for your JSON data.

`eventjet/json` is loosely based how JSON in handled in Go's [`encoding/json`](https://pkg.go.dev/encoding/json) package.

## Installation
```bash
composer require eventjet/json
```

## Usage

```php
use Eventjet\Json\Json;

enum Status: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

final readonly class User
{
    /**
     * @param list<User> $friends
     */
    public function __construct(
        public string $name,
        public Status $status,
        public array $friends,
        public string|null $email = null,
    ) {}
}

$json = '
    {
        "name": "John",
        "status": "active",
        "friends": [
            {"name": "Jane", "status": "inactive", "friends": []}
        ]
    }
';
$user = Json::decode($json, User::class);

echo $user->name; // John
echo $user->status; // Status::Active
echo $user->friends[0]->name; // Jane
echo $user->friends[0]->status; // Status::Inactive
```

## JSON Schema

Schemas use the same decoding and encoding API as other data classes:

```php
use Eventjet\Json\Json;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Type;

$json = '{"type":"object","properties":{"name":{"type":"string"}},"default":null,"x-example":{}}';
$schema = Json::decode($json, Draft202012::class);

assert($schema->type === Type::Object);
assert($schema->properties['name'] instanceof Draft202012);

$encoded = Json::encode($schema);
```

`Draft4`, `Draft6`, `Draft7`, `Draft201909`, and `Draft202012` are ordinary readonly PHP
classes. They implement no serialization interfaces and contain no codec methods.
Their public properties use native types, the `Type` enum, and narrow PHPDoc
annotations. The existing `Field` attribute maps PHP names such as `ref` to JSON
names such as `$ref`.

Nested schemas use their containing dialect's class. Draft 6 and later also
accept native booleans in schema positions. Draft 4 accepts booleans only for
`additionalProperties` and `additionalItems`.

Draft 2019-09 also exposes the legacy `definitions` keyword as typed nested schemas.
It keeps `definitions` and `$defs` separate so both names survive encoding.

Use an explicit union target when the root can also be a boolean:

```php
$schema = Json::decode($json, Draft202012::class . '|bool');
```

A class-only target requires a JSON object. Union decoding accepts a value when
exactly one alternative matches; ambiguous object unions throw `JsonError`.
Matching checks the input types before calling any constructors. Only the selected
alternative is constructed; exceptions from its constructor propagate to the caller.
Schema decoding does not evaluate schemas against instances, resolve references,
or enforce constraints such as uniqueness or positive numeric bounds.

The general codec preserves the JSON structure when a decoded object is encoded:

- Missing properties remain omitted; explicit nulls remain present.
- Unknown keywords survive without being added as dynamic PHP properties.
- Empty objects, arrays, and objects with numeric property names stay distinct.
- Original numeric text is retained while the corresponding PHP value is unchanged.

These guarantees also apply to ordinary data classes. Untyped JSON objects decode
to `stdClass`; typed maps decode to PHP arrays and retain their JSON object shape.
Both constructor parameters and public properties support nested list/map PHPDoc
types and unions. `list<T>` requires a JSON array, `array<K, V>` requires a JSON
object, and `array<T>` accepts either container while checking each element's type.
Public properties use their own declarations; constructor annotations also apply
to promoted properties.

Properties have nullable defaults. JSON presence and original numeric text belong
to the codec's weakly referenced metadata, not to the schema classes. Schema fields are initialized through constructor parameters and cannot be reassigned.
For mutable data classes, encoding reflects edits and unsetting a field removes
its decoded keyword. Replacing a container or adding map keys uses the current
value's shape. Omitted defaults are compared through value snapshots, so unrelated
values such as `NAN` or resources do not prevent decoding.
The metadata belongs to the decoded object instance: cloning or serializing the
PHP object does not copy it. Native PHP numbers still have their usual limits for
calculations; retaining their original JSON text does not provide arbitrary-precision
arithmetic. Whitespace, escaping, and object-member order may change.

To create an empty schema with no present keywords, decode `{}`:

```php
$schema = Json::decode('{}', Draft202012::class);
echo Json::encode($schema);
// {}
```

Schemas also support named constructor arguments, such as
`new Draft202012(type: Type::Object)`. As with other newly constructed data objects,
directly encoding `new Draft202012()`
includes its initialized null properties. Decode `{}` when those keywords should
start absent.
