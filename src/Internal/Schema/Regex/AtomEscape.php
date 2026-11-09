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
    private GroupName $name;

    public function __construct(
        private Cursor $cursor,
        private Groups $groups,
        private ClassEscape $classEscape,
        private CharacterEscape $characterEscape,
    ) {
        $this->name = new GroupName($cursor);
    }

    public function read(): string|null
    {
        $char = $this->cursor->peek();
        if ($char === null) {
            return null;
        }
        if ($char !== '0' && str_contains(Cursor::DECIMAL, $char)) {
            return $this->groups->reference((int) $this->cursor->span(Cursor::DECIMAL));
        }
        if ($char === 'k') {
            return $this->namedReference();
        }
        $class = $this->classEscape->standalone();
        if ($class !== null) {
            return $class;
        }
        $codePoint = $this->characterEscape->read();
        if ($codePoint === null) {
            return null;
        }
        // A lone surrogate can't occur in valid UTF-8, so nothing matches it.
        return CodePoints::isSurrogate($codePoint) ? '(?!)' : CodePoints::literal($codePoint);
    }

    private function namedReference(): string|null
    {
        if ($this->cursor->peek(offset: 1) !== '<') {
            return null;
        }
        $this->cursor->pos += 2;
        $name = $this->name->read();
        return $name === null ? null : $this->groups->referenceNamed($name);
    }
}
