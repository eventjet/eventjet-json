<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function count;

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
}
