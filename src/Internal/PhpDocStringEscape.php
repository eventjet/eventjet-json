<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function chr;
use function intval;
use function is_string;
use function json_decode;
use function sprintf;
use function stripcslashes;
use function substr;

/** @internal */
final class PhpDocStringEscape
{
    public static function decode(string $escape): string|null
    {
        $kind = $escape[1];
        if ($kind !== 'u') {
            return $kind === 'e' ? chr(27) : stripcslashes($escape);
        }
        $code = intval(substr($escape, offset: 3, length: -1), base: 16);
        if ($code > 0x10_ffff || $code >= 0xd800 && $code <= 0xdfff) {
            return null;
        }
        $json = $code <= 0xffff
            ? sprintf('"\\u%04x"', $code)
            : sprintf('"\\u%04x\\u%04x"', 0xd7c0 + ($code >> 10), 0xdc00 + ($code & 0x3ff));
        /** @var mixed $decoded */
        $decoded = json_decode($json);
        return is_string($decoded) ? $decoded : null;
    }
}
