<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use ReflectionProperty;

/** @internal */
final class PublicPropertyHydrator
{
    /**
     * @param class-string $class
     * @param array<array-key, array{property: ReflectionProperty, converter: FieldValueConverter, builtinType: array{string, bool}|null}> $publicProperties
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return list<array{property: ReflectionProperty, value: mixed}>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function prepare(
        string $class,
        array $publicProperties,
        array $values,
        string $path,
    ): array|DecodeError {
        /** @var list<array{property: ReflectionProperty, value: mixed}> $assignments */
        $assignments = [];

        foreach ($values as $inputField => $value) {
            $field = $publicProperties[$inputField] ?? null;

            if ($field === null) {
                continue;
            }

            $builtinType = $field['builtinType'];
            if ($builtinType !== null) {
                $matches = ValueTypeMatcher::matchesName($value, $builtinType[0], $builtinType[1]);
                if ($matches) {
                    $assignments[] = ['property' => $field['property'], 'value' => $value];
                    continue;
                }
            }
            $converted = $field['converter']->convert($class, $value, FieldPath::field($path, (string) $inputField));
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
