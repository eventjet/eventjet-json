<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use ReflectionClass;
use ReflectionException;

use function class_exists;
use function interface_exists;

/** @internal */
final class ClassFieldTypeValidator
{
    /**
     * Rejects a referenced class that JSON cannot identify or construct. Classes that are not loaded pass. Errors in
     * the declarations of the referenced class itself name that class.
     *
     * @throws ReflectionException
     */
    public static function validate(string $field, string $type): DecodeError|null
    {
        if (interface_exists($type)) {
            return UnsupportedFieldClass::interface($field, $type);
        }

        if (!class_exists($type)) {
            return null;
        }

        $typeReflection = new ReflectionClass($type);

        if ($typeReflection->isAbstract()) {
            return UnsupportedFieldClass::abstractClass($field, $type);
        }

        if (!$typeReflection->isFinal()) {
            return UnsupportedFieldClass::nonFinal($field, $type);
        }

        $names = RootTypeValidator::fieldNames($typeReflection);
        return $names instanceof DecodeError ? $names->whileCreating($typeReflection->getName()) : null;
    }
}
