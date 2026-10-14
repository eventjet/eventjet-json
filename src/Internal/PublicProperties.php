<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

use function array_key_exists;

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

    /**
     * Decodes the values of public properties outside the constructor.
     *
     * @param array<array-key, array{property: ReflectionProperty, node: Node}> $properties Keyed by JSON name.
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return list<array{ReflectionProperty, mixed}>|DecodeError
     * @throws JsonException
     */
    public static function decode(array $properties, array $values, string $path): array|DecodeError
    {
        $assignments = [];
        foreach ($properties as $name => $property) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $node = $property['node'];
            $value = $values[$name];
            // Scalars skip the node call, because a call per property slows down hydration measurably.
            $isScalar = $node instanceof ScalarNode && ValueTypeMatcher::matches($value, $node->name, $node->nullable);
            $value = $isScalar ? $value : $node->decode($value, FieldPath::field($path, (string) $name));
            if ($value instanceof DecodeError) {
                return $value;
            }
            $assignments[] = [$property['property'], $value];
        }
        return $assignments;
    }
}
