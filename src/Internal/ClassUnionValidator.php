<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function count;
use function enum_exists;
use function implode;
use function interface_exists;
use function sort;
use function sprintf;

/** @internal */
final class ClassUnionValidator
{
    /**
     * @param class-string $class
     */
    public static function validate(string $class, string $field, ReflectionUnionType $type): DecodeError|null
    {
        $classNames = [];

        foreach ($type->getTypes() as $member) {
            $className = self::className($member);

            if ($className !== null) {
                $classNames[] = $className;
            }
        }

        sort($classNames);

        if (count($classNames) < 2) {
            return null;
        }

        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'class union',
            implode('|', $classNames),
            sprintf('multiple class types: %s. JSON does not identify which class to instantiate.', implode(
                ', ',
                $classNames,
            )),
        );
    }

    private static function className(ReflectionType $type): string|null
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (enum_exists($name) || interface_exists($name)) {
            return null;
        }

        return $name;
    }
}
