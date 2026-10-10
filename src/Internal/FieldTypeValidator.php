<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function in_array;
use function enum_exists;
use function is_a;

/** @internal */
final class FieldTypeValidator
{
    // False identifies a valid non-collection declaration; null leaves unresolved types uncached.
    /**
     * @template T of object
     * @param class-string $class
     * @param ReflectionClass<T> $reflection
     * @throws ReflectionException
     */
    public static function resolveForGraph(
        string $class,
        ReflectionParameter $parameter,
        ReflectionClass $reflection,
        ReflectionMethod|null $constructor,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null {
        $type = $parameter->getType();
        $docComment = $type instanceof ReflectionUnionType && FieldCollectionUnionResolver::hasCollection($type)
            ? PhpDocParameterMarkerCache::constructorDocComment($class, $constructor)
            : false;
        return new ConstructorParameter($parameter, $reflection, $docComment, $docComment)->resolveType($class);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string|false|null $docComment = null,
        bool|null $hasLiteralPhpDoc = null,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null {
        $fieldName = $field->getName();
        $type = $field->getType();

        if ($type instanceof ReflectionIntersectionType) {
            return DecodeError::unsupportedIntersection($class, $fieldName, (string) $type);
        }

        if ($type instanceof ReflectionNamedType) {
            return self::validateNamedType($class, $field, $type, $docComment, $hasLiteralPhpDoc);
        }

        if ($type instanceof ReflectionUnionType) {
            return ClassUnionValidator::resolve($class, $field, $type, $docComment, $hasLiteralPhpDoc);
        }

        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validateNamedType(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionNamedType $type,
        string|false|null $docComment = null,
        bool|null $hasLiteralPhpDoc = null,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null {
        $fieldName = $field->getName();
        $typeName = FieldTypeNameResolver::resolve($field, $type);
        if ($typeName === 'array' || $typeName === ArrayObject::class) {
            return CollectionTypeResolver::resolve($class, $field, $typeName);
        }

        if (in_array($typeName, ['mixed', 'object', stdClass::class], strict: true)) {
            return DecodeError::nonInstantiableField(
                $class,
                $fieldName,
                'unsupported type',
                $typeName,
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            );
        }

        if ($type->isBuiltin() || enum_exists($typeName) && is_a($typeName, BackedEnum::class, allow_string: true)) {
            if ($docComment === false) {
                return false;
            }
            $hasLiteralPhpDoc ??= FieldTypeNameResolver::hasLiteralPhpDoc($field, docComment: $docComment);
            return $hasLiteralPhpDoc ? PhpDocLiteralFieldValidator::validate($class, $field) ?? false : false;
        }
        return ClassFieldTypeValidator::validateNamed($class, $fieldName, $typeName, $type);
    }

}
