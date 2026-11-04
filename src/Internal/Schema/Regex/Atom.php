<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function str_contains;

/**
 * Reads an atom: a character, an escape, a character class, or a group.
 *
 * @internal
 */
final readonly class Atom
{
    /** The characters that can't start an atom. */
    private const string NOT_ATOMS = '*+?{}])|';

    private const string DOT = '[^\n\r\x{2028}\x{2029}]';

    public function __construct(
        private Cursor $cursor,
        private Group $group,
        private CharacterClass $class,
        private AtomEscape $escape,
    ) {}

    public function read(): string|null
    {
        $char = $this->cursor->peek();
        if ($char === null || str_contains(self::NOT_ATOMS, $char)) {
            return null;
        }
        if ($char === '(') {
            return $this->group->read();
        }
        if ($char === '[') {
            return $this->class->read();
        }
        $this->cursor->pos++;
        if ($char === '\\') {
            return $this->escape->read();
        }
        return $char === '.' ? self::DOT : CodePoints::literal(CodePoints::of($char));
    }
}
