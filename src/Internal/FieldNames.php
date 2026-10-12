<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function count;
use function sprintf;

/** @internal */
final class FieldNames
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties
     * @return array<string, string>|DecodeError
     * @phpstan-impure
     */
    public static function resolve(ReflectionClass $class, array $properties): array|DecodeError
    {
        $className = $class->getName();
        try {
            $names = self::discover($class, $properties);
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
        $collision = FieldNameCollisions::validate($class, $names);
        return $collision ?? $names;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties
     * @return array<string, string>|DecodeError
     */
    private static function discover(ReflectionClass $class, array $properties): array|DecodeError
    {
        $names = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Field::class);
            $name = $property->getName();
            if (($property->getModifiers() & ReflectionProperty::IS_PUBLIC) === 0 || $property->isStatic()) {
                return DecodeError::nonInstantiableTarget($class->getName(), sprintf(
                    '#[Field] requires a public instance property; %s is not one.',
                    $name,
                ));
            }
            if (count($attributes) !== 1) {
                return DecodeError::nonInstantiableTarget($class->getName(), sprintf(
                    'Property %s must not repeat #[Field].',
                    $name,
                ));
            }
            $names[$name] = $attributes[0]->newInstance()->name;
        }
        return $names;
    }
}
