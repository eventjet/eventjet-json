<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use JsonException;
use ReflectionException;
use stdClass;

use function is_array;

/** @internal */
final class FieldCollectionUnionValueConverter
{
    /**
     * @param class-string $class
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        string $path,
        FieldCollectionUnionType $type,
        mixed $value,
    ): array|bool|float|int|object|string|null {
        $kind = match (true) {
            is_array($value) => 'array',
            $value instanceof stdClass => 'object',
            default => '',
        };
        $collection = $type->collections[$kind] ?? null;
        if ($collection !== null) {
            return CollectionValueConverter::convert($class, $path, $collection, $value);
        }
        return CollectionUnionValueConverter::convert($class, $path, $type->members, $value, $type->name);
    }
}
