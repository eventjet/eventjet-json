<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function str_contains;

/**
 * Reads a character escape, such as `\n`, `\x41` or `\u{1F600}`, after the backslash.
 *
 * @internal
 */
final readonly class CharacterEscape
{
    private const array CONTROLS = ['t' => 0x09, 'n' => 0x0A, 'v' => 0x0B, 'f' => 0x0C, 'r' => 0x0D];

    private const string SYNTAX_CHARACTERS = '^$\\.*+?()[]{}|/';

    private UnicodeEscape $unicode;

    public function __construct(
        private Cursor $cursor,
    ) {
        $this->unicode = new UnicodeEscape($cursor);
    }

    /** Returns the code point of the escape, or null if it is invalid. */
    public function read(): int|null
    {
        $char = $this->cursor->peek();
        if ($char === null) {
            return null;
        }
        $this->cursor->pos++;
        $control = self::CONTROLS[$char] ?? null;
        if ($control !== null) {
            return $control;
        }
        return match ($char) {
            'c' => $this->controlLetter(),
            '0' => $this->nullCharacter(),
            'x' => $this->cursor->hex(length: 2),
            'u' => $this->unicode->read(),
            default => str_contains(self::SYNTAX_CHARACTERS, $char) ? CodePoints::of($char) : null,
        };
    }

    private function controlLetter(): int|null
    {
        $letter = $this->cursor->peek();
        if ($letter === null) {
            return null;
        }
        if (!str_contains(Cursor::LETTERS, $letter)) {
            return null;
        }
        $this->cursor->pos++;
        return CodePoints::of($letter) % 32;
    }

    private function nullCharacter(): int|null
    {
        $next = $this->cursor->peek();
        return $next !== null && str_contains(Cursor::DECIMAL, $next) ? null : 0;
    }
}
