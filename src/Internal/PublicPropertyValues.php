<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\ConstructorFailure;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use JsonException;
use PropertyHookType;
use ReflectionProperty;
use Throwable;

use function array_key_exists;

/**
 * Kept apart from PublicProperties, so that classes without public properties outside the constructor do not load it.
 *
 * @internal
 */
final class PublicPropertyValues
{
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

    /**
     * Returns the error for an assignment that threw. Values match the declared type, so without a set hook, PHP
     * refused the assignment itself.
     */
    public static function failure(ReflectionProperty $property, Throwable $error): DecodeError
    {
        $hooked = $property->hasHook(PropertyHookType::Set);
        return $hooked
            ? ConstructorFailure::propertyHookThrew($error)
            : UnsupportedTarget::unassignableProperty($error);
    }
}
