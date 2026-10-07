<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function implode;
use function in_array;
use function strtolower;

/** @internal */
final class FieldCollectionUnionResolver
{
    public static function hasCollection(ReflectionUnionType $type): bool
    {
        foreach ($type->getTypes() as $member) {
            if (
                $member instanceof ReflectionNamedType
                && in_array(strtolower($member->getName()), ['array', 'arrayobject'], strict: true)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionNamedType|ReflectionUnionType $native,
    ): FieldCollectionUnionType|DecodeError {
        $type = PhpDocFieldType::resolve($field);
        if ($type === null || $type->name !== '|') {
            return CollectionTypeResolver::invalidDeclaration($class, $field);
        }
        $collections = [];
        $names = [];
        $nativeNames = [];
        $leafNames = [];
        $shapes = [];
        foreach ($type->arguments as $member) {
            $resolved = FieldCollectionUnionMemberResolver::resolve($class, $field, $member);
            if ($resolved instanceof DecodeError) {
                return $resolved;
            }
            if (in_array($resolved['name'], $names, strict: true)) {
                continue;
            }
            if ($resolved['collection'] === null) {
                $leafNames[] = $resolved['name'];
            }
            $kind = $resolved['kind'];
            if ($kind !== null) {
                if (in_array($kind, $shapes, strict: true)) {
                    return DecodeError::nonInstantiableTarget(
                        $class,
                        'Field '
                        . $field->getName()
                        . ' has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                    );
                }
                $shapes[] = $kind;
                if ($resolved['collection'] !== null) {
                    $collections[$kind] = $resolved['collection'];
                }
            }
            $names[] = $resolved['name'];
            $nativeNames[] = $resolved['native'];
        }
        $error = FieldCollectionUnionValidator::validate(
            $class,
            $field,
            $native,
            $nativeNames,
        ) ?? EnumUnionValidator::validateNames($class, $field->getName(), $leafNames);
        return $error ?? new FieldCollectionUnionType(
            $collections,
            new CollectionUnionType($leafNames),
            implode('|', $names),
        );
    }
}
