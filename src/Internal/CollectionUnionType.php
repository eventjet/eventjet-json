<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;
use stdClass;
use Stringable;

use function implode;
use function is_array;
use function is_string;

/** @internal */
final readonly class CollectionUnionType implements Stringable
{
    /** @var list<string> */
    private array $names;
    /** @var array<string, ListType|MapType> */
    private array $collections;

    /** @param list<string|NestedCollectionType> $members */
    public function __construct(
        public array $members,
    ) {
        $names = [];
        $collections = [];
        foreach ($members as $member) {
            if (is_string($member)) {
                $names[] = $member;
                continue;
            }
            $kind = $member->collection instanceof MapType ? 'object' : 'array';
            $collections[$kind] = $member->collection;
        }
        $this->names = $names;
        $this->collections = $collections;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }

    public function hasCollections(): bool
    {
        return $this->collections !== [];
    }

    public function collectionFor(mixed $value): ListType|MapType|null
    {
        $kind = match (true) {
            is_array($value) => 'array',
            $value instanceof stdClass => 'object',
            default => '',
        };
        return $this->collections[$kind] ?? null;
    }

    #[Override]
    public function __toString(): string
    {
        $names = [];
        foreach ($this->members as $member) {
            $names[] = $member instanceof NestedCollectionType ? $member->__toString() : $member;
        }
        return implode('|', $names);
    }
}
