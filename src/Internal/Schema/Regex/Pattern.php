<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function implode;

/**
 * Reads the alternatives of a regex or a group, and the terms in them.
 *
 * @internal
 */
final readonly class Pattern
{
    private const array BOUNDARIES = [
        'b' => '(?:(?<=[A-Za-z0-9_])(?![A-Za-z0-9_])|(?<![A-Za-z0-9_])(?=[A-Za-z0-9_]))',
        'B' => '(?:(?<=[A-Za-z0-9_])(?=[A-Za-z0-9_])|(?<![A-Za-z0-9_])(?![A-Za-z0-9_]))',
    ];

    private Group $group;

    private Atom $atom;

    public function __construct(
        private Cursor $cursor,
        Groups $groups,
    ) {
        $classEscape = new ClassEscape($cursor);
        $characterEscape = new CharacterEscape($cursor);
        $this->group = new Group($this, $cursor, $groups);
        $this->atom = new Atom(
            $cursor,
            $this->group,
            new CharacterClass($cursor, new ClassAtom($cursor, $classEscape, $characterEscape)),
            new AtomEscape($cursor, $groups, $classEscape, $characterEscape),
        );
    }

    public function disjunction(): string|null
    {
        $alternatives = [];
        while (true) {
            $alternative = $this->alternative();
            if ($alternative === null) {
                return null;
            }
            $alternatives[] = $alternative;
            if ($this->cursor->peek() !== '|') {
                return implode('|', $alternatives);
            }
            $this->cursor->pos++;
        }
    }

    private function alternative(): string|null
    {
        $out = '';
        while (true) {
            $end = match ($this->cursor->peek()) {
                null, '|', ')' => true,
                default => false,
            };
            if ($end) {
                return $out;
            }
            $term = $this->term();
            if ($term === null) {
                return null;
            }
            $out .= $term;
        }
    }

    private function term(): string|null
    {
        $char = $this->cursor->peek();
        if ($char === '^' || $char === '$') {
            $this->cursor->pos++;
            return $char;
        }
        $boundary = $char === '\\' ? self::BOUNDARIES[$this->cursor->peek(offset: 1) ?? ''] ?? null : null;
        if ($boundary !== null) {
            $this->cursor->pos += 2;
            return $boundary;
        }
        if ($this->group->atLookaround()) {
            return $this->group->lookaround();
        }
        $atom = $this->atom->read();
        if ($atom === null) {
            return null;
        }
        $quantifier = Quantifier::read($this->cursor);
        return $quantifier === null ? null : $atom . $quantifier;
    }
}
