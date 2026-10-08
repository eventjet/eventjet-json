<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use UnexpectedValueException;

use function implode;
use function preg_replace_callback;
use function substr;

/** @internal */
final class PhpDocLiteralString
{
    public static function parse(string $source): string|null
    {
        $quote = $source[0] ?? '';
        if ($quote !== "'" && $quote !== '"' || substr($source, offset: -1) !== $quote) {
            return null;
        }
        $pattern = $quote === "'"
            ? '/\\\\[\\\\\x27]/'
            : '/\\\\(?:["\\\\nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{[0-9a-fA-F]+\})/';
        try {
            return preg_replace_callback(
                $pattern,
                /**
                 * @param array<array-key, string> $matches
                 * @throws UnexpectedValueException
                 */
                static function (array $matches): string {
                    // The patterns contain no capturing groups, so only the complete match is returned.
                    $decoded = PhpDocStringEscape::decode(implode('', $matches));
                    return $decoded ?? throw new UnexpectedValueException('Invalid Unicode literal escape.');
                },
                substr($source, offset: 1, length: -1),
            );
        } catch (UnexpectedValueException) {
            return null;
        }
    }
}
