<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function chr;
use function hexdec;
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
        if ($kind === "'" || $kind === '"' || $kind === '\\') {
            return $kind;
        }
        if ($kind !== 'u') {
            return $kind === 'e' ? chr(27) : stripcslashes($escape);
        }
        $code = (int) hexdec(substr($escape, offset: 3, length: -1));
        if ($code > 0x10_ffff || $code >= 0xd800 && $code <= 0xdfff) {
            return null;
        }
        $json = $code <= 0xffff
            ? sprintf('"\\u%04x"', $code)
            : sprintf('"\\u%04x\\u%04x"', 0xd800 + (($code - 0x1_0000) >> 10), 0xdc00 + (($code - 0x1_0000) & 0x3ff));
        /** @var mixed $decoded */
        $decoded = json_decode($json);
        return is_string($decoded) ? $decoded : null;
    }
}
