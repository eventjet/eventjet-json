<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function str_contains;

/**
 * Reads an escape outside a character class, after the backslash.
 *
 * @internal
 */
final readonly class AtomEscape
{
    public function __construct(
        private Cursor $cursor,
        private Groups $groups,
        private ClassEscape $classEscape,
        private CharacterEscape $characterEscape,
    ) {}

    public function read(): string|null
    {
        $char = $this->cursor->peek();
        if ($char === null) {
            return null;
        }
        if ($char !== '0' && str_contains(Cursor::DECIMAL, $char)) {
            $number = (int) $this->cursor->span(Cursor::DECIMAL);
            $this->groups->reference($number);
            return '(?:\g{' . $number . '})';
        }
        if ($char === 'k') {
            return $this->namedReference();
        }
        $class = $this->classEscape->standalone();
        if ($class !== null) {
            return $class;
        }
        $codePoint = $this->characterEscape->read();
        return $codePoint === null ? null : CodePoints::literal($codePoint);
    }

    private function namedReference(): string|null
    {
        if ($this->cursor->peek(offset: 1) !== '<') {
            return null;
        }
        $this->cursor->pos += 2;
        $name = $this->cursor->groupName();
        if ($name === null) {
            return null;
        }
        $this->groups->referenceNamed($name);
        return '\k<' . $name . '>';
    }
}
