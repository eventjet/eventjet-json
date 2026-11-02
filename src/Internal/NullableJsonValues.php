<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedJsonValue;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\JsonValue;
use JsonSerializable;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

use function array_any;

/**
 * A `JsonValue|null` field needs a custom serializer: json_encode() writes null for the absent member, which decodes as
 * a JsonValue that holds null. So, like #[Field], such a field requires JsonSerializable and opts the class into it.
 * Kept apart from TargetClass and ClassField, so that only classes that implement JsonSerializable without #[Field],
 * or declare a `JsonValue|null` field, load it.
 *
 * @internal
 */
final class NullableJsonValues
{
    /**
     * Rejects a class with a `JsonValue|null` field unless it implements JsonSerializable.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws DecodeError
     */
    public static function requireSerializer(ReflectionClass $class, string $field): void
    {
        if (!$class->implementsInterface(JsonSerializable::class)) {
            throw UnsupportedJsonValue::withoutSerializer($field);
        }
    }

    /**
     * Rejects a JsonSerializable class without #[Field] unless a public instance property is declared as
     * `JsonValue|null`. Only the native type counts, because a PHPDoc type cannot narrow a field to JsonValue.
     *
     * @param list<ReflectionProperty> $properties
     * @throws DecodeError
     */
    public static function requireDeclared(string $class, array $properties): void
    {
        $declared = array_any($properties, static function (ReflectionProperty $property): bool {
            $type = $property->getType();
            return (
                $property->isPublic()
                && !$property->isStatic()
                && $type instanceof ReflectionNamedType
                && $type->allowsNull()
                && $type->getName() === JsonValue::class
            );
        });
        if (!$declared) {
            throw UnsupportedTarget::jsonSerializable($class);
        }
    }
}
