<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

/**
 * Reads groups and lookaround assertions.
 *
 * @internal
 */
final readonly class Group
{
    private GroupName $name;

    public function __construct(
        private Pattern $pattern,
        private Cursor $cursor,
        private Groups $groups,
    ) {
        $this->name = new GroupName($cursor);
    }

    /** @mutation-free */
    public function atLookaround(): bool
    {
        if ($this->cursor->peek() !== '(' || $this->cursor->peek(offset: 1) !== '?') {
            return false;
        }
        $next = $this->cursor->peek(offset: 2);
        $after = $this->cursor->peek(offset: 3);
        return $next === '=' || $next === '!' || $next === '<' && ($after === '=' || $after === '!');
    }

    public function lookaround(): string|null
    {
        return $this->body($this->cursor->take($this->cursor->peek(offset: 2) === '<' ? 4 : 3));
    }

    public function read(): string|null
    {
        if ($this->cursor->peek(offset: 1) !== '?') {
            $this->cursor->pos++;
            $this->groups->open();
            return $this->body('(');
        }
        $kind = $this->cursor->peek(offset: 2);
        $this->cursor->pos += 3;
        if ($kind === ':') {
            return $this->body('(?:');
        }
        $name = $kind === '<' ? $this->name->read() : null;
        $pcreName = $name === null ? null : $this->groups->openNamed($name);
        return $pcreName === null ? null : $this->body('(?<' . $pcreName . '>');
    }

    /** Reads the disjunction of a group after its opening, and the closing parenthesis. */
    private function body(string $opening): string|null
    {
        $inner = $this->pattern->disjunction();
        if ($inner === null || $this->cursor->peek() !== ')') {
            return null;
        }
        $this->cursor->pos++;
        return $opening . $inner . ')';
    }
}
