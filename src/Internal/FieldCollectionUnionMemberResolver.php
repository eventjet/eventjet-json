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
use function enum_exists;
use function in_array;
use function is_string;

/** @internal */
final class FieldCollectionUnionMemberResolver
{
    /**
     * @param class-string $class
     * @return array{name: string, native: string, kind: string|null, collection: ListType|MapType|TupleType|null}|DecodeError
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): array|DecodeError {
        $resolved = PhpDocItemTypeResolver::resolve($field, $type);
        if ($resolved instanceof NestedCollectionType) {
            $collection = $resolved->collection;
            $error = CollectionTypeValidator::validate($class, $field->getName(), $collection);
            return $error ?? self::collection($collection, (string) $resolved);
        }
        if ($type->name === 'array{}') {
            $collection = TupleTypeResolver::resolve($field, $type->entries);
            if ($collection === null) {
                return CollectionTypeResolver::invalidDeclaration($class, $field);
            }
            $error = CollectionTypeValidator::validate($class, $field->getName(), $collection);
            return $error ?? self::collection($collection, (string) $collection);
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
        $isClass = class_exists($name) && !enum_exists($name);
        return ['name' => $name, 'native' => $name, 'kind' => $isClass ? 'object' : null, 'collection' => null];
    }

    /** @return array{name: string, native: string, kind: string, collection: ListType|MapType|TupleType} */
    private static function collection(ListType|MapType|TupleType $collection, string $name): array
    {
        return [
            'name' => $name,
            'native' => $collection instanceof MapType && $collection->arrayObject ? ArrayObject::class : 'array',
            'kind' => $collection instanceof MapType ? 'object' : 'array',
            'collection' => $collection,
        ];
    }
}
