<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use InvalidArgumentException;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function ctype_digit;
use function in_array;

/**
 * The reference tokens of a keyword location, read without the schema: a token at a schema position is a keyword, and
 * the keyword decides whether an index or a name comes before the next schema position.
 *
 * @internal
 */
final readonly class KeywordPath
{
    private const array REFERENCES = ['$ref', '$dynamicRef', '$recursiveRef'];

    /** Keywords followed by an index or a name and then a schema position. */
    private const array CONTAINERS = [
        'allOf',
        'anyOf',
        'oneOf',
        'prefixItems',
        'properties',
        'patternProperties',
        '$defs',
        'definitions',
        'dependentSchemas',
        'dependencies',
        'dependentRequired',
    ];

    /**
     * @param list<string> $parts
     * @param list<int> $keywords The positions of the keywords in the parts.
     */
    private function __construct(
        public array $parts,
        public array $keywords,
    ) {}

    /** @throws InvalidArgumentException */
    public static function of(string $keywordLocation): self
    {
        $parts = JsonPointer::parse($keywordLocation);
        $keywords = [];
        $count = count($parts);
        for ($position = 0; $position < $count; $position += self::isContainer($parts, $position) ? 2 : 1) {
            $keywords[] = $position;
        }
        return new self($parts, $keywords);
    }

    /**
     * Returns whether the path goes through a reference.
     *
     * @mutation-free
     */
    public function references(): bool
    {
        return $this->referencePositions() !== [];
    }

    /**
     * Returns whether the last part is a keyword other than a reference: the keyword that failed.
     *
     * @mutation-free
     */
    public function endsWithKeyword(): bool
    {
        $last = count($this->parts) - 1;
        return (
            in_array($last, $this->keywords, strict: true)
            && !in_array($last, $this->referencePositions(), strict: true)
        );
    }

    /**
     * The position of the first reference keyword, or null if there is none.
     */
    public function firstReference(): int|null
    {
        return $this->referencePositions()[0] ?? null;
    }

    /**
     * The parts up to and including the first reference keyword, or all of them if there is none: the ones that lead
     * through the document of the schema.
     *
     * @return list<string>
     */
    public function beforeReferences(): array
    {
        $first = $this->firstReference();
        return $first === null ? $this->parts : $this->upTo($first);
    }

    /**
     * The parts after the last reference keyword, or all of them if there is none: the ones inside the schema that
     * the last reference leads to.
     *
     * @return list<string>
     */
    public function afterReferences(): array
    {
        $last = array_slice($this->referencePositions(), offset: -1)[0] ?? -1;
        return array_slice($this->parts, $last + 1);
    }

    /**
     * The parts up to and including the given position.
     *
     * @return list<string>
     */
    public function upTo(int $position): array
    {
        return array_slice($this->parts, offset: 0, length: $position + 1);
    }

    /**
     * @return list<int>
     * @mutation-free
     */
    private function referencePositions(): array
    {
        return array_values(array_filter($this->keywords, fn(int $position): bool => in_array(
            $this->parts[$position] ?? '',
            self::REFERENCES,
            strict: true,
        )));
    }

    /**
     * @param list<string> $parts
     * @pure
     */
    private static function isContainer(array $parts, int $position): bool
    {
        $keyword = $parts[$position] ?? '';
        $indexedItems = $keyword === 'items' && ctype_digit($parts[$position + 1] ?? '');
        return $indexedItems || in_array($keyword, self::CONTAINERS, strict: true);
    }
}
