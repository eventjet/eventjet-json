<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionNamedType;
use ReflectionParameter;
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
        ReflectionParameter $parameter,
        ReflectionUnionType $type,
    ): DecodeError|null {
        $classNames = [];

        foreach ($type->getTypes() as $member) {
            $className = self::className($parameter, $member);

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
            $parameter->getName(),
            'multiple class types:',
            implode(', ', $classNames),
            '. JSON does not identify which class to instantiate.',
        );
    }

    private static function className(ReflectionParameter $parameter, ReflectionType $type): string|null
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = ParameterTypeNameResolver::resolve($parameter, $type);

        if (enum_exists($name) || interface_exists($name)) {
            return null;
        }

        return $name;
    }
}
