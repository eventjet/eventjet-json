<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function array_values;

/** @internal */
final class FieldCollectionUnionResolver
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionNamedType|ReflectionUnionType $native,
    ): CollectionUnionType|DecodeError {
        $type = PhpDocFieldType::resolve($field);
        if ($type === null || $type->name !== '|') {
            return CollectionTypeResolver::invalidDeclaration($class, $field);
        }
        $members = [];
        foreach ($type->arguments as $member) {
            $resolved = FieldCollectionUnionMemberResolver::resolve($class, $field, $member);
            if ($resolved instanceof DecodeError) {
                return $resolved;
            }
            foreach ($resolved instanceof CollectionUnionType ? $resolved->members : [$resolved] as $value) {
                $members[(string) $value] = $value;
            }
        }
        $union = new CollectionUnionType(array_values($members));
        $shapeError = CollectionUnionShapeValidator::validate($class, $field->getName(), $union);
        if ($shapeError !== null) {
            return $shapeError;
        }
        $error = FieldCollectionUnionValidator::validate($class, $field, $native, $union);
        if ($error !== null) {
            return $error;
        }
        $error = PhpDocLiteralUnionValidator::validate($class, $field->getName(), $union->names());
        if ($error !== null) {
            return $error;
        }
        $error = EnumUnionValidator::validateNames($class, $field->getName(), $union->names());
        return $error ?? $union;
    }
}
