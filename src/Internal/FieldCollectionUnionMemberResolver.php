<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;
use function in_array;
use function is_string;

/** @internal */
final class FieldCollectionUnionMemberResolver
{
    /**
     * @param class-string $class
     * @return string|NestedCollectionType|DecodeError
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): string|NestedCollectionType|DecodeError {
        $resolved = PhpDocItemTypeResolver::resolve($field, $type);
        if ($resolved instanceof NestedCollectionType) {
            $collection = $resolved->collection;
            $error = CollectionTypeValidator::validate($class, $field->getName(), $collection);
            return $error ?? $resolved;
        }
        $isLiteral = $type->arguments === [] && in_array($type->name, ['null', 'true', 'false'], strict: true);
        $name = $isLiteral ? $type->name : $resolved;
        if (!is_string($name)) {
            return CollectionTypeResolver::invalidDeclaration($class, $field);
        }
        $name = class_exists($name) ? new ReflectionClass($name)->getName() : $name;
        if ($name === ArrayObject::class) {
            return CollectionTypeResolver::invalidDeclaration($class, $field);
        }
        return $name;
    }
}
