<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function chr;
use function hexdec;
use function is_int;
use function octdec;
use function preg_replace_callback;
use function str_replace;
use function substr;

use const PREG_UNMATCHED_AS_NULL;

/**
 * Decodes quoted string tokens like phpstan/phpdoc-parser 2.x. Unlike PHP, it keeps `\$` as is, and it encodes any
 * code point of a `\u{…}` escape, including surrogates and values above U+10FFFF.
 *
 * @internal
 */
final class PhpDocLiteralString
{
    private const array REPLACEMENTS = [
        '\\' => '\\',
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'f' => "\f",
        'v' => "\v",
        'e' => "\x1B",
    ];

    public static function unescape(string $quoted): string
    {
        $content = substr($quoted, offset: 1, length: -1);
        if (($quoted[0] ?? '') === "'") {
            return str_replace(['\\\\', "\\'"], replace: ['\\', "'"], subject: $content);
        }
        return (string) preg_replace_callback(
            '~\\\\(?:(?<simple>[\\\\nrtfve])|[xX](?<hex>[0-9a-fA-F]{1,2})|(?<octal>[0-7]{1,3})|u\{(?<unicode>[0-9a-fA-F]+)\})~',
            self::escape(...),
            str_replace('\\"', replace: '"', subject: $content),
            flags: PREG_UNMATCHED_AS_NULL,
        );
    }

    /** @param array<array-key, string|null> $matches */
    private static function escape(array $matches): string
    {
        $simple = $matches['simple'] ?? null;
        $hex = $matches['hex'] ?? null;
        $unicode = $matches['unicode'] ?? null;
        return match (true) {
            $simple !== null => self::REPLACEMENTS[$simple] ?? '',
            $hex !== null => chr((int) hexdec($hex)),
            $unicode !== null => self::utf8(self::codePoint($unicode)),
            default => chr((int) octdec($matches['octal'] ?? '')),
        };
    }

    /**
     * phpdoc-parser casts the value of a `\u{…}` escape to int, which gives 0 for a value beyond PHP's integer range.
     */
    private static function codePoint(string $hex): int
    {
        $code = hexdec($hex);
        return is_int($code) ? $code : 0;
    }

    private static function utf8(int $code): string
    {
        if ($code <= 0x7F) {
            return chr($code);
        }
        if ($code <= 0x7FF) {
            return chr(($code >> 6) + 0xC0) . chr(($code & 0x3F) + 0x80);
        }
        if ($code <= 0xFFFF) {
            return chr(($code >> 12) + 0xE0) . chr((($code >> 6) & 0x3F) + 0x80) . chr(($code & 0x3F) + 0x80);
        }
        if ($code <= 0x1F_FFFF) {
            return (
                chr(($code >> 18) + 0xF0)
                . chr((($code >> 12) & 0x3F) + 0x80)
                . chr((($code >> 6) & 0x3F) + 0x80)
                . chr(($code & 0x3F) + 0x80)
            );
        }
        return "\u{FFFD}";
    }
}
