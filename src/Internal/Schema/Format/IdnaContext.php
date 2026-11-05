<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function preg_match;
use function str_contains;

/**
 * The CONTEXTO rules and the DISALLOWED exceptions of RFC 5892, which UTS 46 doesn't check.
 *
 * @internal
 */
final class IdnaContext
{
    private const string PVALID = '\p{Ll}\p{Lo}\p{Nd}\p{Lm}\p{Mn}\p{Mc}\-\x{00DF}\x{03C2}\x{06FD}\x{06FE}\x{0F0B}\x{3007}';
    private const string CONTEXTUAL = '\x{200C}\x{200D}\x{00B7}\x{0375}\x{05F3}\x{05F4}\x{30FB}';

    public static function allows(string $label): bool
    {
        $permitted = preg_match('/^[' . self::PVALID . self::CONTEXTUAL . ']*$/uD', $label) === 1;
        $disallowed = preg_match('/[\x{0640}\x{07FA}\x{302E}\x{302F}\x{3031}-\x{3035}\x{303B}]/u', $label) === 1;
        if (!$permitted || $disallowed) {
            return false;
        }
        return (
            self::middleDot($label)
            && self::greekKeraia($label)
            && self::hebrewPunctuation($label)
            && self::katakanaMiddleDot($label)
            && self::arabicIndicDigits($label)
        );
    }

    private static function middleDot(string $label): bool
    {
        return (
            !str_contains($label, "\u{00B7}")
            || preg_match('/^(?:[^\x{00B7}]|(?<=l)\x{00B7}(?=l))*$/u', $label) === 1
        );
    }

    private static function greekKeraia(string $label): bool
    {
        return !str_contains($label, "\u{0375}") || preg_match('/\x{0375}(?!\p{Greek})/u', $label) !== 1;
    }

    private static function hebrewPunctuation(string $label): bool
    {
        return preg_match('/(?<!\p{Hebrew})[\x{05F3}\x{05F4}]/u', $label) !== 1;
    }

    private static function katakanaMiddleDot(string $label): bool
    {
        return (
            !str_contains($label, "\u{30FB}")
            || preg_match('/(?!\x{30FB})[\p{Hiragana}\p{Katakana}\p{Han}]/u', $label) === 1
        );
    }

    private static function arabicIndicDigits(string $label): bool
    {
        $arabicIndic = preg_match('/[\x{0660}-\x{0669}]/u', $label) === 1;
        $extendedArabicIndic = preg_match('/[\x{06F0}-\x{06F9}]/u', $label) === 1;
        return !($arabicIndic && $extendedArabicIndic);
    }
}
