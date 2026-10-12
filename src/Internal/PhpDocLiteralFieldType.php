<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function enum_exists;

/** @internal */
final class PhpDocLiteralFieldType
{
    public static function accepts(ReflectionType|null $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return !ClassUnionValidator::hasCollection($type);
        }
        return (
            $type instanceof ReflectionNamedType
            && ($type->isBuiltin() ? $type->getName() !== 'array' : enum_exists($type->getName()))
        );
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
    ): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null {
        $type = $field->getType();
        if ($type instanceof ReflectionUnionType) {
            return self::union($class, $field, $type);
        }
        $resolved = FieldTypeValidator::validate($class, $field);
        return $resolved === false ? PhpDocLiteralFieldValidator::validate($class, $field) ?? false : $resolved;
    }

    /** @throws ReflectionException */
    public static function converter(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection,
    ): FieldValueConverter {
        $literals = $collection === null ? PhpDocLiteralField::resolve($field) : null;
        return $literals === null
            ? new FieldValueConverter($field, $collection)
            : new PhpDocLiteralFieldValueConverter($field, $literals);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private static function union(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): CollectionUnionType|DecodeError|false|null {
        $literals = PhpDocLiteralField::resolve($field);
        $error =
            ClassUnionValidator::validate($class, $field, $type)
            ?? EnumUnionValidator::validate($class, $field->getName(), $type, $literals)
            ?? PhpDocLiteralFieldValidator::validateUnresolved($class, $field, $literals);
        if ($error !== null) {
            return $error;
        }
        $members = ClassUnionValidator::members($class, $field, $type);
        if ($literals === null || $members instanceof DecodeError) {
            return $members;
        }
        $union = new CollectionUnionType($literals);
        return CollectionUnionTypeValidator::validate($class, $field->getName(), $union) ?? $union;
    }
}
