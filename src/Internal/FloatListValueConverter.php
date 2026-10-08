<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function array_key_exists;
use function is_float;
use function is_int;
use function sprintf;

/** @internal */
final class FloatListValueConverter
{
    /**
     * @param class-string $class
     * @param list<mixed> $value
     * @return list<mixed>|DecodeError
     */
    public static function convert(string $class, string $path, array $value): array|DecodeError
    {
        $index = 0;
        while (array_key_exists($index, $value)) {
            if (!is_float($value[$index]) && !is_int($value[$index])) {
                return DecodeError::fieldTypeMismatch(
                    $class,
                    sprintf('%s[%d]', $path, $index),
                    'float',
                    $value[$index],
                );
            }
            if (is_int($value[$index])) {
                $value[$index] = (float) $value[$index];
            }
            ++$index;
        }
        return $value;
    }
}
