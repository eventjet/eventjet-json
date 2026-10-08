<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;

use function enum_exists;
use function sprintf;

/** @internal */
final class RootTypeValidator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    public static function validate(ReflectionClass $class): DecodeError|null
    {
        $className = $class->getName();

        if ($class->isInterface()) {
            return DecodeError::nonInstantiableTarget(
                $className,
                'Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            );
        }

        if ($class->isAbstract()) {
            return DecodeError::nonInstantiableTarget(
                $className,
                'Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            );
        }

        FieldMapping::resolve($class);

        if ($class->implementsInterface(JsonSerializable::class) && !FieldMapping::annotated($class)) {
            return DecodeError::jsonSerializableTarget($className);
        }

        $constructor = $class->getConstructor();

        if ($constructor !== null && !$constructor->isPublic()) {
            $visibility = $constructor->isPrivate() ? 'private' : 'protected';

            return DecodeError::nonInstantiableTarget($className, sprintf(
                'Target class has a %s constructor, which cannot be called to create the object.',
                $visibility,
            ));
        }

        $classIsNonBackedEnum = self::isNonBackedEnum($className);

        if ($classIsNonBackedEnum) {
            return DecodeError::nonBackedEnum($className, $className);
        }

        return null;
    }

    private static function isNonBackedEnum(string $type): bool
    {
        return enum_exists($type) && !new ReflectionEnum($type)->isBacked();
    }
}
