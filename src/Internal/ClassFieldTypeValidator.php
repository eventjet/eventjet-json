<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;

use function class_exists;
use function interface_exists;

/** @internal */
final class ClassFieldTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, string $field, string $type): DecodeError|null
    {
        if (interface_exists($type)) {
            return DecodeError::nonInstantiableField($class, $field, 'interface', $type);
        }

        if (!class_exists($type)) {
            return null;
        }

        $typeReflection = new ReflectionClass($type);

        if ($typeReflection->isAbstract()) {
            return DecodeError::nonInstantiableField($class, $field, 'abstract class', $type);
        }

        if (!$typeReflection->isFinal()) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'non-final class',
                $type,
                '. Values may be subclasses, whose runtime class JSON does not identify.',
            );
        }

        return null;
    }
}
