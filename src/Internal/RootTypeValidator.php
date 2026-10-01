<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;

use function enum_exists;

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
            return DecodeError::nonInstantiableTarget($className, 'an interface', 'a concrete implementation');
        }

        if ($class->isAbstract()) {
            return DecodeError::nonInstantiableTarget($className, 'an abstract class', 'a concrete subclass');
        }

        if ($class->implementsInterface(JsonSerializable::class)) {
            return DecodeError::jsonSerializableTarget($className);
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
