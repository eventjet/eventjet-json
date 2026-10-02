<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function assert;
use function class_exists;
use function in_array;
use function interface_exists;

/** @internal */
final class ListItemTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError|null
    {
        $itemType = ListItemTypeResolver::resolveDeclaration($field);

        if ($itemType === null || in_array($itemType, ['bool', 'float', 'int', 'string'], strict: true)) {
            return null;
        }

        if (interface_exists($itemType)) {
            return DecodeError::nonInstantiableField($class, $field->getName(), 'interface', $itemType);
        }

        assert(class_exists($itemType), description: 'Resolved list item type must name a class or enum.');
        $itemTypeReflection = new ReflectionClass($itemType);

        if ($itemTypeReflection->isAbstract()) {
            return DecodeError::nonInstantiableField($class, $field->getName(), 'abstract class', $itemType);
        }

        return !$itemTypeReflection->isFinal()
            ? DecodeError::nonInstantiableField(
                $class,
                $field->getName(),
                'non-final class',
                $itemType,
                '. Values may be subclasses, whose runtime class JSON does not identify.',
            )
            : null;
    }
}
