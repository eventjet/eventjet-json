<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use JsonException;
use ReflectionProperty;

/** @internal */
final class PublicPropertyHydrator
{
    /**
     * @param array<array-key, array{property: ReflectionProperty, node: Node}> $publicProperties
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return list<array{property: ReflectionProperty, value: mixed}>|DecodeError
     * @throws JsonException
     */
    public static function prepare(array $publicProperties, array $values, string $path): array|DecodeError
    {
        $assignments = [];

        foreach ($values as $inputField => $value) {
            $field = $publicProperties[$inputField] ?? null;

            if ($field === null) {
                continue;
            }

            $converted = $field['node']->decode($value, FieldPath::field($path, (string) $inputField));
            if ($converted instanceof DecodeError) {
                return $converted;
            }
            $assignments[] = ['property' => $field['property'], 'value' => $converted];
        }

        return $assignments;
    }

    /** @param list<array{property: ReflectionProperty, value: mixed}> $assignments */
    public static function assign(object $object, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $assignment['property']->setValue($object, $assignment['value']);
        }
    }
}
