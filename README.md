# eventjet/json

Type-safe JSON encoding and decoding for PHP 8.2+.

This library gives you two things:

- **Strongly-typed JSON → object decoding** using reflection and constructor promotion.
- **A small JSON type/validation DSL** that lets you describe JSON structures and get structured validation errors.

It is built on top of native `json_encode` / `json_decode`, but adds type safety, clear error messages, and a convenient way to describe JSON shapes.

## Table of contents

- [Installation](#installation)
- [Quick start](#quick-start)
  - [Decoding JSON into a DTO](#decoding-json-into-a-dto)
- [Decoding rules](#decoding-rules)
  - [Supported parameter types](#supported-parameter-types)
  - [Required vs optional parameters](#required-vs-optional-parameters)
  - [Nested objects](#nested-objects)
  - [Enums](#enums)
  - [Union types](#union-types)
  - [Unknown and extra JSON fields](#unknown-and-extra-json-fields)
- [Arrays and the `#[ArrayOf]` attribute](#arrays-and-the-arrayof-attribute)
- [Error handling with `JsonError`](#error-handling-with-jsonerror)
- [Validation API (`JsonType` DSL)](#validation-api-jsontype-dsl)
  - [Primitive types](#primitive-types)
  - [Arrays](#arrays)
  - [Objects](#objects)
  - [Unions](#unions)
  - [Validation results and issues](#validation-results-and-issues)
  - [Canonicalization and equality](#canonicalization-and-equality)
- [Common patterns and recipes](#common-patterns-and-recipes)
- [Edge cases and limitations](#edge-cases-and-limitations)
- [Public API reference](#public-api-reference)
- [Development](#development)

## Installation

The package is available on Packagist as [`eventjet/json`](https://packagist.org/packages/eventjet/json).

```bash
composer require eventjet/json
```

Requirements:

- PHP **8.2** or higher
- `ext-json`

## Quick start

### Decoding JSON into a DTO

The main entry point for decoding is `Eventjet\Json\Json::decode`.

```php
use Eventjet\Json\Json;

class User
{
    public function __construct(
        public string $name,
    ) {}
}

$json = '{"name":"John"}';

/** @var User $user */
$user = Json::decode($json, User::class);

$user->name; // "John"
```

`Json::decode(string $json, string $class)`:

- Expects the JSON **root** to be an object.
- Uses the target class’ **constructor** signature to decide what to read from JSON.
- Matches JSON fields to constructor parameters **by name**.
- Enforces the **declared parameter types**.
- Returns an instance of the requested class on success.
- Throws `Eventjet\Json\JsonError` if decoding or type checks fail.

Unknown fields in the JSON input are ignored.

#### Optional parameters and defaults

Optional parameters behave like in normal PHP constructors:

```php
use Eventjet\Json\Json;

class User
{
    public function __construct(
        public string $name,
        public int $age = 18,
    ) {}
}

// age omitted → default value 18 is used
$json = '{"name": "John"}';
$user = Json::decode($json, User::class);
// $user->age === 18
```

If a parameter is optional and its default value is `null`, an explicit `null` in JSON is allowed:

```php
class MaybeLabel
{
    public function __construct(
        public ?string $label = null,
    ) {}
}

Json::decode('{"label": null}', MaybeLabel::class); // OK, label = null
Json::decode('{}', MaybeLabel::class);               // OK, label = null (default)
```

If an **optional but non-nullable** parameter is present with `null` in JSON, decoding fails with a `JsonError`.

## Decoding rules

### Supported parameter types

`Json::decode` looks at the constructor parameter type hints and behaves as follows.

#### Scalars

Supported scalar types:

- `string`
- `int`
- `float`
- `bool`

Example:

```php
class Scalars
{
    public function __construct(
        public string $name,
        public int $age,
        public float $score,
        public bool $active,
    ) {}
}

$json = '{"name":"John","age":42,"score":3.14,"active":true}';
$dto = Json::decode($json, Scalars::class);
```

If a value has the wrong type (e.g. a number for `string $name`), decoding fails with a `JsonError`.

#### `mixed`

A `mixed` parameter can be populated with any JSON value **except an object**:

```php
class MixedExample
{
    public function __construct(
        public mixed $val,
    ) {}
}

Json::decode('{"val": "John"}', MixedExample::class);  // OK
Json::decode('{"val": 123}', MixedExample::class);       // OK
Json::decode('{"val": [1,2,3]}', MixedExample::class);   // OK

Json::decode('{"val": {"foo":"bar"}}', MixedExample::class); // throws JsonError
```

If a JSON object is used for a `mixed` parameter, decoding fails and the error explains that a specific class type is required instead of `mixed`.

#### `object` (disallowed)

Using the built-in `object` type is **not** allowed for constructor parameters. You must use a concrete class instead.

```php
class Bad
{
    public function __construct(
        public object $obj,
    ) {}
}

Json::decode('{"obj": {"foo": "bar"}}', Bad::class); // throws JsonError
```

If a parameter is typed as `object`, decoding always fails and the error instructs you to use a specific class name.

#### Class types (nested objects)

If a parameter is typed with a **class name**, and the JSON value is an object, `Json::decode` recursively decodes it into that class.

```php
class Address
{
    public function __construct(
        public string $street,
    ) {}
}

class User
{
    public function __construct(
        public string $name,
        public Address $address,
    ) {}
}

$json = '{"name":"John","address":{"street":"Main"}}';
$user = Json::decode($json, User::class);

$user->address instanceof Address; // true
```

If the JSON value is an object but the parameter type is not a known class (or is a scalar/other unsupported type), decoding fails with a `JsonError`.

#### Enums

Backed enums (string-backed or int-backed) are supported for parameters and array items.

```php
enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}

class Post
{
    public function __construct(
        public Status $status,
    ) {}
}

Json::decode('{"status": "draft"}', Post::class); // OK, Status::Draft
```

Rules:

- The enum must be a **backed enum**. Non-backed enums cause decoding to fail with a `JsonError`.
- The JSON value must be an `int` or `string` matching a case; otherwise decoding fails with a `JsonError`.

#### Union types

Union-typed parameters are supported. The library uses PHP’s own type system and error handling for non-object values.

There is **special handling** when the JSON value is an object:

- If the union contains **no class types**, and the JSON value is an object, decoding fails with a `JsonError` explaining that there are no class types in the union that can handle the object.
- If the union contains **exactly one class type**, and the JSON value is an object, that class is used to decode the object.
- If the union contains **more than one class type**, and the JSON value is an object, decoding fails with a `JsonError` explaining that unions of multiple object types are not supported.

Example (single class in union):

```php
class Foo
{
    public function __construct(public string $name) {}
}

class Container
{
    public function __construct(
        public string|int|Foo $val,
    ) {}
}

Json::decode('{"val": {"name":"X"}}', Container::class); // val is Foo
Json::decode('{"val": "X"}', Container::class);            // val is string
Json::decode('{"val": 123}', Container::class);             // val is int
```

### Required vs optional parameters

- If a constructor parameter is **required** (no default value) and the JSON object does **not** contain that key, decoding fails with a `JsonError` describing the missing property.
- If a parameter is **optional** (has a default value) and the JSON object does **not** contain that key, the default value is used.
- If a parameter is optional and its default is `null`, explicit `null` in JSON is allowed and treated as `null`.
- If a parameter is optional but **non-nullable** and JSON explicitly gives `null`, decoding fails with a `JsonError` describing the type mismatch.

### Nested objects

Nested objects are handled by recursively calling `Json::decode` internally.

```php
class Wrapper
{
    public function __construct(
        public MaybeLabel $obj,
    ) {}
}

$json = '{"obj": {"label": "X"}}';
$wrapper = Json::decode($json, Wrapper::class);

$wrapper->obj instanceof MaybeLabel; // true
```

If a required nested object is missing from JSON, decoding fails with a `JsonError` describing the missing property.

### Unknown and extra JSON fields

Extra fields in the JSON object that do not correspond to constructor parameters are **ignored**. They do not cause an error and are not stored anywhere.

## Arrays and the `#[ArrayOf]` attribute

Arrays are supported via the `Eventjet\Json\ArrayOf` attribute applied to `array`-typed constructor parameters.

```php
use Eventjet\Json\ArrayOf;

class Tags
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        #[ArrayOf('string')]
        public array $tags,
    ) {}
}

$json = '{"tags": ["foo", "bar"]}';
$dto = Json::decode($json, Tags::class);
```

`ArrayOf` is defined as:

```php
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ArrayOf
{
    /**
     * @param 'string'|'int'|'float'|'null'|'bool'|class-string|self $itemType
     */
    public function __construct(public string|self $itemType) {}
}
```

Supported `itemType` values:

- `'string'`, `'int'`, `'float'`, `'bool'`, `'null'` for scalar arrays
- a **class-string** for arrays of objects or enums
- another `ArrayOf` instance for nested arrays

Examples:

#### Array of scalars

```php
class Scalars
{
    /** @param int[] $numbers */
    public function __construct(
        #[ArrayOf('int')]
        public array $numbers,
    ) {}
}

Json::decode('{"numbers": [1, 2, 3]}', Scalars::class);
```

If an item has the wrong type (e.g. a string in an `int[]`), decoding fails with a `JsonError` describing the mismatch.

#### Array of objects

```php
class Item
{
    public function __construct(public string $name) {}
}

class ItemList
{
    /** @param Item[] $items */
    public function __construct(
        #[ArrayOf(Item::class)]
        public array $items,
    ) {}
}

$json = '{"items": [{"name":"A"}, {"name":"B"}]}';
$list = Json::decode($json, ItemList::class);
```

If a non-object appears where an object is expected, decoding fails with a `JsonError` describing the mismatch.

If the referenced class does not exist, decoding fails with a `JsonError` describing the unknown class.

#### Nested arrays

Nested arrays use nested `ArrayOf` instances:

```php
class Matrix
{
    /** @param string[][] $data */
    public function __construct(
        #[ArrayOf(new ArrayOf('string'))]
        public array $data,
    ) {}
}

Json::decode('{"data": [["a", "b"], ["c", "d"]]}', Matrix::class);
```

If the JSON value for an `array` parameter is not an array at all, decoding fails with a `JsonError` describing the mismatch.

Attribute rules:

- **Missing** `#[ArrayOf]` on an `array` parameter causes decoding to fail with a `JsonError` describing the missing attribute.
- **Multiple** `#[ArrayOf]` on the same parameter causes decoding to fail with a `JsonError` describing the duplicate attributes.

Docblock array types (PHPStan/Psalm):

- Docblock types such as `list<string>`, `array<int, Foo>`, or `array<string, Bar>` are not enforced by the decoder today; `#[ArrayOf(...)]` is the runtime source of truth.
- Until docblock support lands, you should keep `#[ArrayOf(...)]` and your docblock types (e.g., `list<...>`) in sync manually.
- Supporting PHPStan/Psalm-style docblock types is an explicit goal of the project and may be added in a future release.

## Error handling with `JsonError`

All decoding errors are reported via the `Eventjet\Json\JsonError` exception.

```php
use Eventjet\Json\Json;
use Eventjet\Json\JsonError;

try {
    $dto = Json::decode($json, MyDto::class);
} catch (JsonError $e) {
    // Handle invalid JSON or type mismatches
    echo $e->getMessage();
}
```

`JsonError`:

- Extends `RuntimeException`.
- Has a factory method `JsonError::decodeFailed(string|null $message, ?Throwable $previous = null): self`.
- Uses exception code `0`.

The exact messages for common failure modes are considered part of the public API and are covered by the test suite, but are intentionally not repeated here.

## Validation API (`JsonType` DSL)

Separate from decoding into PHP objects, the library provides a small DSL for describing and validating JSON structures.

The main entry point is `Eventjet\Json\Type\JsonType` and its helpers.

### Primitive types

```php
use Eventjet\Json\Type\JsonType;

$typeString = JsonType::string();   // JSON string
$typeNumber = JsonType::number();   // JSON number
$typeBool   = JsonType::boolean();  // JSON boolean
$typeTrue   = JsonType::true();     // literal true
$typeFalse  = JsonType::false();    // literal false
$typeNull   = JsonType::null();     // literal null
```

Each type implements `__toString()` using a TypeScript-like syntax, for example `string`, `number`, `boolean`, `true`, `false`, `null`.

### Arrays

Use `JsonType::array(JsonType $elementType)` to describe arrays of a certain type.

```php
use Eventjet\Json\Type\JsonType;

$type = JsonType::array(JsonType::string());

// ["foo", "bar"] is valid
$result = $type->validate('["foo","bar"]');
$result->isValid(); // true

// [42] is not valid (int instead of string)
$result = $type->validate('[42]');
$result->isValid(); // false
```

Incorrect items produce issues with the item index in the `path` field of the issue.

`(string) JsonType::array(JsonType::string())` is `Array<string>`.

### Objects

Objects are described using `JsonType::object(array $members)` where each member is a `Member`.

```php
use Eventjet\Json\Type\JsonType;
use Eventjet\Json\Type\Member;

$type = JsonType::object([
    'name' => Member::required(JsonType::string()),
    'age'  => Member::optional(JsonType::number()),
]);

// Valid
$type->validate('{"name":"John"}')->isValid();

// Missing required member
$result = $type->validate('{}');
$result->isValid(); // false
```

Rules:

- `Member::required(JsonType $type)` marks a required object property.
- `Member::optional(JsonType $type)` marks an optional property.
- Missing required members produce issues with message "Missing required member." and the member name as `path`.
- Wrong types on members produce issues with a descriptive message and the member name as `path`.

String representation examples:

- `JsonType::object(['name' => Member::required(JsonType::string())])` → `{name: string}`
- `JsonType::object(['name' => Member::optional(JsonType::string())])` → `{name?: string}`
- `JsonType::object(['name' => Member::required(JsonType::string()), 'age' => Member::optional(JsonType::number())])` → `{name: string, age?: number}`

### Unions

Unions combine multiple `JsonType` instances.

You can create a union explicitly:

```php
use Eventjet\Json\Type\JsonType;

$type = JsonType::union(JsonType::string(), JsonType::null());
```

or by using the instance method `or`:

```php
$type = JsonType::true()->or(JsonType::false());
```

Examples:

```php
// string | null
$type = JsonType::union(JsonType::string(), JsonType::null());

$type->validate('"foo"')->isValid(); // true
$type->validate('null')->isValid();   // true

$result = $type->validate('42');
// invalid: result contains an issue describing the expected union and the actual value
```

Unions can be nested in arrays and objects as well:

- `JsonType::array(JsonType::union(JsonType::string(), JsonType::null()))`
- `JsonType::object(['name' => Member::required(JsonType::union(JsonType::string(), JsonType::null()))])`

### Validation results and issues

All `JsonType` instances expose a common entry point for validation:

```php
use Eventjet\Json\Type\JsonType;

$type = JsonType::string();
$result = $type->validate('"foo"');

if ($result->isValid()) {
    // OK
} else {
    foreach ($result->issues as $issue) {
        echo $issue->message . ' at ' . $issue->path . PHP_EOL;
    }
}
```

Key types:

- `Eventjet\Json\Type\ValidationResult`
  - `public readonly array $issues` – list of `ValidationIssue`
  - `public static function valid(): self`
  - `public static function error(string $message, string $path): self`
  - `public static function merge(array $results): self` – merge multiple results
  - `public function isValid(): bool`
- `Eventjet\Json\Type\ValidationIssue`
  - `public readonly string $message`
  - `public readonly string $path`
  - `public function __toString(): string` – returns `"<message> at <path>"`
  - `public function equals(self $other): bool`

`JsonType::validate(string $json): ValidationResult` decodes the JSON and returns a `ValidationResult`:

- If the JSON is invalid, you get a single issue with message "Invalid JSON" and path `""`.
- Otherwise it validates the decoded value against the type.

### Canonicalization and equality

`JsonType` provides a `canonicalize()` method to normalize types so they can be compared by string:

```php
use Eventjet\Json\Type\JsonType;
use Eventjet\Json\Type\Member;

$a = JsonType::object([
    'name' => Member::required(JsonType::string()),
    'age'  => Member::optional(JsonType::number()),
]);

$b = JsonType::object([
    'age'  => Member::optional(JsonType::number()),
    'name' => Member::required(JsonType::string()),
]);

(string) $a->canonicalize() === (string) $b->canonicalize(); // true
```

The canonical form hides irrelevant ordering differences. The test suite ensures that:

- Object member order does not matter.
- Union order does not matter, including unions inside arrays and object members.
- Required vs optional members do matter.

## Common patterns and recipes

### DTOs with promoted properties

The library is designed to work naturally with constructor promotion:

```php
class User
{
    public function __construct(
        public string $name,
        public int $age,
        public bool $active = true,
    ) {}
}

$user = Json::decode('{"name":"John","age":42}', User::class);
```

### Optional fields

Use default values for optional fields and (optionally) nullable types when `null` is meaningful:

```php
class Profile
{
    public function __construct(
        public string $username,
        public ?string $bio = null,
    ) {}
}

// bio omitted → null
Json::decode('{"username":"john"}', Profile::class);

// bio explicit null → null
Json::decode('{"username":"john","bio":null}', Profile::class);
```

### Combining validation and decoding

A common pattern is to validate incoming JSON first, then decode only if valid:

```php
use Eventjet\Json\Json;
use Eventjet\Json\Type\JsonType;
use Eventjet\Json\Type\Member;

$json = /* incoming JSON string */ '';

$schema = JsonType::object([
    'name' => Member::required(JsonType::string()),
    'age'  => Member::optional(JsonType::number()),
]);

$result = $schema->validate($json);

if (!$result->isValid()) {
    // Return all issues to the caller
    foreach ($result->issues as $issue) {
        // e.g. log or serialize into an HTTP error response
    }

    return;
}

// At this point, $json has the expected shape; decode into your DTO
$dto = Json::decode($json, MyDto::class);
```

## Edge cases and limitations

The behavior below is guaranteed by the public API and the test suite.

- The JSON **root** for `Json::decode` must be an object.
- The target class **must have a constructor**; otherwise decoding fails.
- Constructor parameter names must match JSON property names.
- Unknown JSON properties are ignored.
- Type `object` is not allowed for constructor parameters; use a specific class instead.
- `mixed` cannot receive JSON objects; use a specific class type for such fields.
- Unions of multiple class types cannot be used when the JSON value is an object; only one class type is supported.
- Arrays:
  - Require exactly one `#[ArrayOf]` attribute when used with `array` parameters.
  - Nested arrays require nested `ArrayOf` definitions.
  - PHPStan/Psalm docblock array types (e.g., `list<string>`) are not enforced yet. Keep them in sync with `#[ArrayOf(...)]`. Future support is planned.
- Enums:
  - Must be backed enums.
  - The JSON value type must match the backing type (string or int).
- Maps (objects with arbitrary keys):
  - Not supported yet. Planned support will first come via a dedicated attribute and later via docblock types like `array<string, ...>`.
- Validation (`JsonType`):
  - Works on decoded JSON values; invalid JSON yields a single "Invalid JSON" issue.
  - Uses TypeScript-style string representations for debugging and logging.

## Development

This section is only relevant if you want to work on the library itself.

Run the test suite:

```bash
composer install
vendor/bin/phpunit
```

Static analysis and quality tools (see `composer.json`):

```bash
# Static analysis
vendor/bin/phpstan
vendor/bin/psalm

# Coding standard
composer run cs-check

# Mutation testing
vendor/bin/infection -jmax

# Full check (all of the above)
composer run check
```
