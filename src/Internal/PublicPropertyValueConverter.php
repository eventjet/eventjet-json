<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;
use stdClass;

use function is_array;

/** @internal */
final class PublicPropertyValueConverter
{
    public static function convert(ReflectionNamedType $type, mixed $value): mixed
    {
        if ($type->getName() !== 'array' || !$value instanceof stdClass && !is_array($value)) {
            return $value;
        }

        return ObjectValueConverter::convertArrayValue($value);
    }
}
