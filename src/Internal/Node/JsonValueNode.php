<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedJsonValue;
use Eventjet\Json\JsonValue;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use ReflectionClass;

/**
 * Wraps any JSON value, null included, in a JsonValue. A field declared as `JsonValue|null` stays null only if its
 * member is absent, so it decodes with this node too.
 *
 * @internal
 */
final readonly class JsonValueNode implements Node
{
    /** @param bool $nullable Whether the field is declared as `JsonValue|null`. */
    public function __construct(
        public bool $nullable,
    ) {}

    /** JSON numbers too large for a float decode as INF, which a JsonValue rejects. */
    #[Override]
    public function decode(mixed $value, string $path): JsonValue
    {
        try {
            return new JsonValue($value);
        } catch (InvalidArgumentException $error) {
            throw InvalidValue::jsonValue($path, $error);
        }
    }

    /**
     * Rejects a `JsonValue|null` field in a class that doesn't implement JsonSerializable.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws DecodeError
     */
    public function requireSerializer(ReflectionClass $class, string $field): void
    {
        if ($this->nullable && !$class->implementsInterface(JsonSerializable::class)) {
            throw UnsupportedJsonValue::nullableWithoutSerializer($field);
        }
    }

    /** Claims every JSON value. UnionBuilder rejects unions that have another member. */
    #[Override]
    public function claims(): array
    {
        $accepts = ['others' => 'accepts', 'enums' => [], 'values' => []];
        return [
            'null' => $accepts,
            'true' => $accepts,
            'false' => $accepts,
            'int' => $accepts,
            'float' => $accepts,
            'string' => $accepts,
            'array' => $accepts,
            'stdClass' => $accepts,
        ];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return JsonValue::class;
    }
}
