<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function array_unique;
use function class_exists;
use function in_array;
use function interface_exists;
use function sort;

/** @internal */
final class FieldCollectionUnionValidator
{
    /**
     * @param class-string $class
     * @param list<string> $names
     * @throws ReflectionException
     */
    public static function validate(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionNamedType|ReflectionUnionType $native,
        array $names,
    ): DecodeError|null {
        $expected = [];
        $members = $native instanceof ReflectionUnionType ? $native->getTypes() : [$native];
        foreach ($members as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return DecodeError::unsupportedIntersection($class, $field->getName(), (string) $member);
            }
            $name = FieldTypeNameResolver::resolve($field, $member);
            $name = class_exists($name) || interface_exists($name) ? new ReflectionClass($name)->getName() : $name;
            $expected[] = $name;
            $isNonEncodable = ClassFieldTypeValidator::isNonEncodable($name);
            if (in_array($name, ['array', ArrayObject::class], strict: true) || $isNonEncodable) {
                continue;
            }
            $error = FieldTypeValidator::validateNamedType($class, $field, $member);
            if ($error instanceof DecodeError) {
                return $error;
            }
        }
        if ($native instanceof ReflectionNamedType && $native->allowsNull()) {
            $expected[] = 'null';
        }
        $names = array_unique($names);
        sort($names);
        sort($expected);
        return $names === $expected ? null : CollectionTypeResolver::invalidDeclaration($class, $field);
    }
}
