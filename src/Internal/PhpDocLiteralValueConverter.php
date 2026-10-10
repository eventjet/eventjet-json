<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;

/** @internal */
final class PhpDocLiteralValueConverter
{
    /** @param class-string $class */
    public static function convert(
        string $class,
        string $path,
        string $type,
        mixed $value,
    ): string|int|float|bool|BackedEnum|DecodeError|null {
        $matches = PhpDocLiteral::matches($type, $value);
        return $matches || $type === 'null' && $value === null
            ? PhpDocLiteral::value($type)
            : DecodeError::fieldTypeMismatch($class, $path, $type, $value);
    }
}
