<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function is_int;

/**
 * Reads a character class, such as `[^a-z\d]`.
 *
 * @internal
 */
final readonly class CharacterClass
{
    public function __construct(
        private Cursor $cursor,
        private ClassAtom $atom,
    ) {}

    public function read(): string|null
    {
        $this->cursor->pos++;
        $negated = $this->cursor->peek() === '^';
        if ($negated) {
            $this->cursor->pos++;
        }
        $items = '';
        while (true) {
            $char = $this->cursor->peek();
            if ($char === ']') {
                break;
            }
            $item = $this->item();
            if ($item === null) {
                return null;
            }
            $items .= $item;
        }
        $this->cursor->pos++;
        if ($items === '') {
            return $negated ? '[\x{0}-\x{10FFFF}]' : '(?!)';
        }
        return ($negated ? '[^' : '[') . $items . ']';
    }

    /** Reads one atom or range of the class. */
    private function item(): string|null
    {
        $start = $this->atom->read();
        $next = $this->cursor->peek(offset: 1);
        $range = match ($next) {
            ']' => false,
            default => $this->cursor->peek() === '-',
        };
        if (!$range) {
            return is_int($start) ? CodePoints::literal($start) : $start;
        }
        $this->cursor->pos++;
        $end = $this->atom->read();
        if (!is_int($start) || !is_int($end) || $start > $end) {
            return null;
        }
        return CodePoints::literal($start) . '-' . CodePoints::literal($end);
    }
}
