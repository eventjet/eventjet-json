<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function array_filter;
use function array_key_exists;
use function count;
use function sprintf;

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
     * @return array<string, string>|DecodeError JSON names keyed by property name.
     */
    public static function resolve(ReflectionClass $class, array $properties, array $annotated): array|DecodeError
    {
        $className = $class->getName();
        try {
            $names = self::read($className, $annotated);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($className, $error);
        }
        if ($names instanceof DecodeError) {
            return $names;
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            return DecodeError::nonInstantiableTarget(
                $className,
                '#[Field] requires the target class to implement JsonSerializable.',
            );
        }
        return self::collision($className, $properties, $names) ?? $names;
    }

    /**
     * @param class-string $class
     * @param array<ReflectionProperty> $properties
     * @return array<string, string>|DecodeError
     */
    private static function read(string $class, array $properties): array|DecodeError
    {
        $names = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Field::class);
            $name = $property->getName();
            $publicInstance = self::isPublicInstance($property);
            if (!$publicInstance) {
                return DecodeError::nonInstantiableTarget($class, sprintf(
                    '#[Field] requires a public instance property; %s is not one.',
                    $name,
                ));
            }
            if (count($attributes) !== 1) {
                return DecodeError::nonInstantiableTarget($class, sprintf(
                    'Property %s must not repeat #[Field].',
                    $name,
                ));
            }
            $names[$name] = $attributes[0]->newInstance()->name;
        }
        return $names;
    }

    /**
     * Rejects two public instance properties that use the same JSON name.
     *
     * @param class-string $class
     * @param list<ReflectionProperty> $properties
     * @param array<string, string> $names
     */
    private static function collision(string $class, array $properties, array $names): DecodeError|null
    {
        $owners = [];
        foreach (array_filter($properties, self::isPublicInstance(...)) as $property) {
            $name = $property->getName();
            $wireName = $names[$name] ?? $name;
            if (array_key_exists($wireName, $owners)) {
                return DecodeError::nonInstantiableTarget($class, sprintf(
                    'Properties %s and %s use the same JSON field name "%s".',
                    $owners[$wireName],
                    $name,
                    $wireName,
                ));
            }
            $owners[$wireName] = $name;
        }
        return null;
    }

    private static function isPublicInstance(ReflectionProperty $property): bool
    {
        return (
            ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
            === ReflectionProperty::IS_PUBLIC
        );
    }
}
