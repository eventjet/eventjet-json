<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function in_array;

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
            return self::validateUnionType($class, $field, $type);
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

        return ClassFieldTypeValidator::validateNamed($class, $fieldName, $typeName, $type);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function validateUnionType(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): CollectionUnionType|DecodeError|false|null {
        $hasCollection = FieldCollectionUnionResolver::hasCollection($type);
        if ($hasCollection) {
            return FieldCollectionUnionResolver::resolve($class, $field, $type);
        }
        $fieldName = $field->getName();
        $classUnionError = ClassUnionValidator::validate($class, $field, $type);

        if ($classUnionError !== null) {
            return $classUnionError;
        }

        $enumUnionError = EnumUnionValidator::validate($class, $fieldName, $type);

        if ($enumUnionError !== null) {
            return $enumUnionError;
        }

        $memberResults = [];
        $nonEncodableError = null;
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionIntersectionType) {
                return DecodeError::unsupportedIntersection($class, $fieldName, (string) $member);
            }

            $name = FieldTypeNameResolver::resolve($field, $member);
            $isNonEncodable = ClassFieldTypeValidator::isNonEncodable($name);
            if ($isNonEncodable) {
                $nonEncodableError ??= DecodeError::nonBackedEnum($class, $name, $fieldName);
                continue;
            }
            $error = self::validateNamedType($class, $field, $member);

            if ($error instanceof DecodeError) {
                return $error;
            }
            $memberResults[] = $error;
        }

        if ($memberResults === []) {
            return $nonEncodableError;
        }
        return in_array(null, $memberResults, strict: true) ? null : false;
    }
}
