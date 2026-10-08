<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use BackedEnum;
use Eventjet\Json\DecodeError;
use JsonSerializable;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function enum_exists;
use function in_array;
use function is_a;

/** @internal */
final class FieldTypeValidator
{
    // False identifies a valid non-collection declaration; null leaves unresolved types uncached.
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null {
        $fieldName = $field->getName();
        $type = $field->getType();

        if ($type instanceof ReflectionIntersectionType) {
            return DecodeError::unsupportedIntersection($class, $fieldName, (string) $type);
        }

        if ($type instanceof ReflectionNamedType) {
            return self::validateNamedType($class, $field, $type);
        }

        if ($type instanceof ReflectionUnionType) {
            return ClassUnionValidator::resolve($class, $field, $type);
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
            return PhpDocFieldType::hasPhpDoc($field)
                ? PhpDocLiteralFieldValidator::validate($class, $field) ?? false
                : false;
        }
        return ClassFieldTypeValidator::validateNamed($class, $fieldName, $typeName, $type);
    }

    public static function isNonEncodable(string $type): bool
    {
        if (in_array($type, ['resource', 'open-resource', 'closed-resource'], strict: true)) {
            return true;
        }

        return (
            enum_exists($type)
            && !new ReflectionEnum($type)->isBacked()
            && !is_a($type, JsonSerializable::class, allow_string: true)
        );
    }
}
