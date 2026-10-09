<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function count;
use function str_contains;
use function strspn;

/** @internal */
final readonly class PhpDocType
{
    /**
     * @param list<self> $arguments
     * @param list<PhpDocTupleEntry> $entries
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
        public array $entries = [],
    ) {}

    /** @param non-empty-list<self> $types */
    public static function compound(string $operator, array $types): self
    {
        return count($types) === 1 ? $types[0] : new self($operator, $types);
    }

    /** @return array{self, self}|null */
    public function argumentPair(): array|null
    {
        $arguments = $this->arguments;
        if (count($arguments) !== 2) {
            return null;
        }
        $first = $arguments[0];
        $second = array_pop($arguments);
        return [$first, $second];
    }

    public function isPlainName(string $name): bool
    {
        return $this->name === $name && $this->arguments === [];
    }

    public function containsLiteral(): bool
    {
        return (
            in_array($this->name, ['null', 'true', 'false'], strict: true)
            || self::literalSyntax($this->name)
            || $this->name === '|'
            && array_any($this->arguments, static fn(self $member): bool => $member->containsLiteral())
        );
    }

    /** @pure */
    public static function literalSyntax(string $name): bool
    {
        return strspn($name, characters: "'\"0123456789.+-", length: 1) === 1 || str_contains($name, '::');
    }
}
