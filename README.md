# eventjet/json

A better alternative to `json_decode($json, true)` + `array{foo: string, bar: int}` PHPStan shapes.

This library provides **real runtime type checking** into actual PHP objects, not just static analysis.

**Goal:** Round-trip `json_encode` → `Json::decode` should restore the original value.

> ⚠️ This isn't fully achieved yet, and perfect round-trips may never be possible (e.g., no tracking of omitted fields).

```php
\Eventjet\Json\Json::decode(string $json, ?string $type = null): mixed
```

## Installation

```bash
composer require eventjet/json
```

## Usage

### Objects

```php
use Eventjet\Json\Json;

class Person
{
    public function __construct(
        public string $name,
        public int $age,
    ) {
    }
}

$person = Json::decode('{"name": "Alice", "age": 30}', Person::class);
// Person { name: "Alice", age: 30 }
```

### Default Values

Missing JSON fields use PHP default values—there's no special "omitted" handling.

```php
class WithDefault
{
    public function __construct(
        public string $required,
        public string $optional = 'default',
    ) {
    }
}

$obj = Json::decode('{"required": "value"}', WithDefault::class);
// required: "value", optional: "default"
```

### Lists

Requires a `@param` docblock with `list<T>` syntax:

```php
class WithTags
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public array $tags,
    ) {
    }
}

$obj = Json::decode('{"tags": ["php", "json"]}', WithTags::class);
```

Root-level: `Json::decode('[1, 2, 3]', 'list<int>')`

### Maps

Requires a `@param` docblock with `array<string, T>` syntax:

```php
class Config
{
    /**
     * @param array<string, int> $settings
     */
    public function __construct(
        public array $settings,
    ) {
    }
}

$config = Json::decode('{"settings": {"timeout": 30, "retries": 3}}', Config::class);
```

Root-level: `Json::decode('{"alice": 100}', 'array<string, int>')`

**Note:** Only string keys are supported. Integer keys (`array<int, T>`) and single-argument syntax (`array<T>`, `T[]`) are not supported.

### Enums

Only backed enums are supported: `Json::decode('"active"', Status::class)`

### Union Types

```php
class Result
{
    public function __construct(
        public string|int $value,
    ) {
    }
}

$result = Json::decode('{"value": "text"}', Result::class); // value is "text"
$result = Json::decode('{"value": 42}', Result::class);     // value is 42
```

**Resolution precedence** (when multiple branches could match):

1. Primitive types (`null`, `string`, `int`, `float`, `bool`)
2. Class types
3. Map types (`array<string, T>`)
4. List types (`list<T>`)

For `array<string, string>|Foo`, an empty object `{}` decodes as `Foo`, not an empty map.

## Differences from `json_decode()`

This library requires explicit types and performs strict validation:

```php
// json_decode() returns stdClass or array, no validation
$data = json_decode('{"name": "Alice"}');

// Json::decode() requires a type and validates the structure
$person = Json::decode('{"name": "Alice", "age": 30}', Person::class);
```

For objects and arrays, you must specify the type:
- Objects: `Json::decode($json, Person::class)`
- Lists: `Json::decode($json, 'list<int>')`
- Maps: `Json::decode($json, 'array<string, int>')`

Extra JSON fields not in your class are ignored, so you only need to map the fields you care about. Types are checked strictly—`"123"` in JSON won't become an int.

**Nullable vs optional:**

- **Nullable** (`string|null`): JSON field can have `null` as value, but must be present
- **Optional** (has default): JSON field can be omitted entirely

```php
class Example
{
    public function __construct(
        public string|null $nullable,       // {"nullable": null} valid, {} not
        public string $optional = 'default', // {} valid, uses default
    ) {
    }
}
```

## Exceptions

All exceptions extend `JsonDecodeException`. See the `@throws` tag on `Json::decode()` for details.

## Limitations

- Array notations other than `list<T>` and `array<string, T>` are not supported (e.g., `T[]`, `array<T>`, `array<int, T>`)
- Unit enums are not supported; only backed enums work
- Class unions like `Foo|Bar` where both are classes are not supported
- Docblock-only types like `non-empty-string` or `positive-int` are not supported
- Only constructor parameters are mapped; properties outside the constructor are ignored
- JSON field names must match constructor parameter names exactly

## "I want more mapping options!"

The classes you decode into should mirror the JSON structure exactly. Then transform those objects into whatever you want—just like you did before with `json_decode($json, true)`.

## Development

```bash
composer check  # Run all checks
```

| Command | Description |
|---------|-------------|
| `composer cs-check` | Check code style |
| `composer cs-fix` | Fix code style |
| `composer phpstan` | Run PHPStan |
| `composer psalm` | Run Psalm |
| `composer test` | Run PHPUnit |
| `composer infection` | Run mutation testing |

## Requirements

- PHP 8.2+

## License

MIT
