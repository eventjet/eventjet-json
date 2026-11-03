<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedJsonValue;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\JsonValue;
use ReflectionClass;

/**
 * Finds the public instance properties declared as `JsonValue|null`. json_encode() would write null for such a
 * property, which decodes as a JsonValue that holds null, so a serializer must leave it out instead. Like #[Field], such
 * a property therefore opts a JsonSerializable class into decoding. Only the native type counts, because a PHPDoc type
 * cannot narrow a property to JsonValue.
 *
 * @internal
 */
final class NullableJsonValues
{
    /** @var array<class-string, array<string, true>> */
    private static array $names = [];

    /**
     * Returns the names of the properties as keys.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, true>
     */
    public static function of(ReflectionClass $class): array
    {
        return self::$names[$class->getName()] ??= self::declared($class);
    }

    /**
     * Rejects a class without #[Field] that implements JsonSerializable but has no such property, and JsonValue, which
     * gets here only at the root or as a JsonType item, because fields compile it to a node of their own.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws DecodeError
     */
    public static function requireOne(ReflectionClass $class): void
    {
        if ($class->getName() === JsonValue::class) {
            throw UnsupportedJsonValue::outsideFields();
        }
        $names = self::of($class);
        if ($names === []) {
            throw UnsupportedTarget::jsonSerializable($class->getName());
        }
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, true>
     */
    private static function declared(ReflectionClass $class): array
    {
        $names = [];
        foreach ($class->getProperties() as $property) {
            $holds = (string) $property->getType() === '?' . JsonValue::class;
            if (!$property->isPublic() || $property->isStatic() || !$holds) {
                continue;
            }
            $names[$property->getName()] = true;
        }
        return $names;
    }
}
