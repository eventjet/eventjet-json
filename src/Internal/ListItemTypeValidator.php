<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final class ListItemTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError|null
    {
        $itemType = CollectionTypeResolver::resolveListItem($field);

        return $itemType === null ? null : ClassFieldTypeValidator::validate($class, $field->getName(), $itemType);
    }
}
