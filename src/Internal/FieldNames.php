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
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     * @phpstan-impure
     */
    public static function resolve(ReflectionClass $class): array|DecodeError
    {
        $className = $class->getName();
        $cached = self::$names[$className] ?? null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $names = self::discover($class);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($className, $error);
        }
        if ($names instanceof DecodeError) {
            return $names;
        }
        if ($names === []) {
            return $class->implementsInterface(JsonSerializable::class)
                ? DecodeError::jsonSerializableTarget($className)
                : (self::$names[$className] = []);
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            return DecodeError::nonInstantiableTarget(
                $className,
                '#[Field] requires the target class to implement JsonSerializable.',
            );
        }
        $collision = FieldNameCollisions::validate($class, $names);
        return $collision ?? (self::$names[$className] = $names);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, string>|DecodeError
     */
    private static function discover(ReflectionClass $class): array|DecodeError
    {
        $names = [];
        foreach ($class->getProperties() as $property) {
            $attributes = $property->getAttributes(Field::class);
            if ($attributes === []) {
                continue;
            }
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
