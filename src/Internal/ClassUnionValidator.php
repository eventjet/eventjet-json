<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function count;
use function enum_exists;
use function implode;
use function interface_exists;
use function sort;

/** @internal */
final class ClassUnionValidator
{
    /**
     * @param class-string $class
     */
    public static function validate(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): DecodeError|null {
        $classNames = [];

        foreach ($type->getTypes() as $member) {
            $className = self::className($field, $member);

            if ($className !== null) {
                $classNames[] = $className;
            }
        }

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

    private static function className(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): string|null
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = FieldTypeNameResolver::resolve($field, $type);

        if (enum_exists($name) || interface_exists($name)) {
            return null;
        }

        return $name;
    }
}
