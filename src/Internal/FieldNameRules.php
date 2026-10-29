<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\DecodeFailure;
use Eventjet\Json\Internal\Error\InvalidFieldMapping;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function array_filter;
use function array_key_exists;
use function count;

/**
 * Reads the #[Field] names of a class and checks the rules for them. Kept apart from TargetClass, so that classes
 * without #[Field] do not load it.
 *
 * @internal
 */
final class FieldNameRules
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties The properties of the class.
     * @param array<ReflectionProperty> $annotated The properties that declare #[Field], including private ancestor
     *     properties.
     * @return array<string, string> JSON names keyed by property name.
     * @throws DecodeFailure
     */
    public static function resolve(ReflectionClass $class, array $properties, array $annotated): array
    {
        try {
            $names = self::read($annotated);
        } catch (DecodeFailure $error) {
            throw $error;
        } catch (Throwable $error) {
            throw UnsupportedTarget::unreadable($error);
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            throw InvalidFieldMapping::withoutJsonSerializable();
        }
        self::rejectCollisions($properties, $names);
        return $names;
    }

    /**
     * @param array<ReflectionProperty> $properties
     * @return array<string, string>
     * @throws DecodeFailure
     */
    private static function read(array $properties): array
    {
        $names = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Field::class);
            $name = $property->getName();
            $publicInstance = self::isPublicInstance($property);
            if (!$publicInstance) {
                throw InvalidFieldMapping::nonPublicProperty($name);
            }
            if (count($attributes) !== 1) {
                throw InvalidFieldMapping::repeated($name);
            }
            $names[$name] = $attributes[0]->newInstance()->name;
        }
        return $names;
    }

    /**
     * Rejects two public instance properties that use the same JSON name.
     *
     * @param list<ReflectionProperty> $properties
     * @param array<string, string> $names
     * @throws DecodeFailure
     */
    private static function rejectCollisions(array $properties, array $names): void
    {
        $owners = [];
        foreach (array_filter($properties, self::isPublicInstance(...)) as $property) {
            $name = $property->getName();
            $wireName = $names[$name] ?? $name;
            if (array_key_exists($wireName, $owners)) {
                throw InvalidFieldMapping::collision($owners[$wireName], $name, $wireName);
            }
            $owners[$wireName] = $name;
        }
    }

    private static function isPublicInstance(ReflectionProperty $property): bool
    {
        return (
            ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
            === ReflectionProperty::IS_PUBLIC
        );
    }
}
