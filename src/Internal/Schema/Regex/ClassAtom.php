<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

/**
 * Reads one atom of a character class.
 *
 * @internal
 */
final readonly class ClassAtom
{
    /** Escapes that mean something else inside a class than outside. */
    private const array ESCAPES = ['b' => 0x08, '-' => 0x2D];

    public function __construct(
        private Cursor $cursor,
        private ClassEscape $classEscape,
        private CharacterEscape $characterEscape,
    ) {}

    /** @return int|string|null A code point, a class escape as PCRE, or null if invalid. */
    public function read(): int|string|null
    {
        $char = $this->cursor->peek();
        if ($char === null) {
            return null;
        }
        $this->cursor->pos++;
        if ($char !== '\\') {
            return CodePoints::of($char);
        }
        $escape = self::ESCAPES[$this->cursor->peek() ?? ''] ?? null;
        if ($escape !== null) {
            $this->cursor->pos++;
            return $escape;
        }
        return $this->classEscape->member() ?? $this->characterEscape->read();
    }
}
