<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

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
        /** @var mixed $item */
        foreach ($value as $index => $item) {
            if (is_float($item)) {
                continue;
            }
            if (is_int($item)) {
                $value[$index] = (float) $item;
                continue;
            }
            return DecodeError::fieldTypeMismatch($class, sprintf('%s[%d]', $path, $index), 'float', $item);
        }
        return $value;
    }
}
