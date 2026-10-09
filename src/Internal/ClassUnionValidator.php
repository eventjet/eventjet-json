<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_any;
use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function sort;
use function strtolower;

/** @internal */
final class ClassUnionValidator
{
    /**
     * @param class-string $class
     */
    private static function validate(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): DecodeError|null {
        $classNames = array_values(array_filter(
            array_map(static fn(ReflectionType $member): string|null => ClassFieldTypeValidator::classUnionMember(
                $field,
                $member,
            ), $type->getTypes()),
            static fn(string|null $name): bool => $name !== null,
        ));

        return self::validateNames($class, $field->getName(), $classNames);
    }

    /**
     * @param class-string $class
     * @param list<string> $classNames
     */
    public static function validateNames(string $class, string $field, array $classNames): DecodeError|null
    {
        sort($classNames);

        if (count($classNames) < 2) {
            return null;
        }

        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'multiple class types:',
            implode(', ', $classNames),
            '. JSON does not identify which class to instantiate.',
        );
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function resolve(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): CollectionUnionType|DecodeError|false|null {
        $hasCollection = self::hasCollection($type);
        if ($hasCollection) {
            return FieldCollectionUnionResolver::resolve($class, $field, $type);
        }
        $fieldName = $field->getName();
        $literals = PhpDocLiteralField::resolve($field);
        $classError = self::validate($class, $field, $type);
        if ($classError !== null) {
            return $classError;
        }
        $unionError = EnumUnionValidator::validate($class, $fieldName, $type, $literals);

        if ($unionError !== null) {
            return $unionError;
        }

        $memberResults = [];
        $nonEncodableError = null;
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionIntersectionType) {
                return DecodeError::unsupportedIntersection($class, $fieldName, (string) $member);
            }

            $name = FieldTypeNameResolver::resolve($field, $member);
            $isNonEncodable = FieldTypeValidator::isNonEncodable($name);
            if ($isNonEncodable) {
                $nonEncodableError ??= DecodeError::nonBackedEnum($class, $name, $fieldName);
                continue;
            }
            $error = FieldTypeValidator::validateNamedType($class, $field, $member);

            if ($error instanceof DecodeError) {
                return $error;
            }
            $memberResults[] = $error;
        }

        if ($memberResults === []) {
            return $nonEncodableError;
        }
        if ($literals !== null) {
            $literalUnion = new CollectionUnionType($literals);
            return CollectionUnionTypeValidator::validate($class, $fieldName, $literalUnion) ?? $literalUnion;
        }
        return in_array(null, $memberResults, strict: true) ? null : false;
    }

    private static function hasCollection(ReflectionUnionType $type): bool
    {
        return array_any(
            $type->getTypes(),
            static fn(ReflectionType $member): bool => $member instanceof ReflectionNamedType
            && in_array(strtolower($member->getName()), ['array', 'arrayobject'], strict: true),
        );
    }
}
