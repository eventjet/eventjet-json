<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/** @internal */
final class PublicProperties
{
    /**
     * Compiles the public instance properties that are not constructor parameters, keyed by JSON name.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @return array<string, array{property: ReflectionProperty, node: Node}>|DecodeError
     * @throws ReflectionException
     */
    public static function compile(ReflectionClass $class, array $names): array|DecodeError
    {
        $constructorFields = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructorFields[$parameter->getName()] = true;
        }
        $properties = [];
        foreach ($class->getProperties() as $property) {
            $publicInstance =
                ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
                === ReflectionProperty::IS_PUBLIC;
            if (!$publicInstance || ($constructorFields[$property->getName()] ?? false)) {
                continue;
            }
            $node = PublicPropertyTypeValidator::validate($property);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $properties[$names[$property->getName()] ?? $property->getName()] = [
                'property' => $property,
                'node' => $node,
            ];
        }
        return $properties;
    }
}
