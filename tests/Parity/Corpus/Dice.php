<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use Random\Engine\Mt19937;
use Random\Randomizer;

use function count;
use function max;
use function strlen;
use function substr;

/**
 * Seeded random choices, so that each corpus is the same on every run.
 *
 * @internal
 */
final readonly class Dice
{
    private Randomizer $random;

    public function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function chance(int $percent): bool
    {
        return $this->int(1, 100) <= $percent;
    }

    /** Returns the first string with the given chance, and the second otherwise. */
    public function either(int $percent, string $then, string $else = ''): string
    {
        $chosen = $this->chance($percent);
        return $chosen ? $then : $else;
    }

    public function int(int $min, int $max): int
    {
        return $this->random->getInt($min, $max);
    }

    /**
     * @template T
     * @param non-empty-list<T> $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)] ?? $items[0];
    }

    /**
     * Applies one to the given number of edits at random positions between the margins: each deletes a character, or
     * inserts or replaces one with a string from the pool.
     *
     * @param non-empty-list<string> $pool
     */
    public function mutate(string $text, int $edits, int $margin, array $pool): string
    {
        for ($edit = $this->int(1, $edits); $edit > 0; $edit--) {
            $position = $this->int($margin, max($margin, strlen($text) - 1 - $margin));
            $deletes = $this->chance(33);
            $insert = $deletes ? '' : $this->pick($pool);
            $replace = $deletes ? 1 : $this->int(0, 1);
            $text = substr($text, offset: 0, length: $position) . $insert . substr($text, offset: $position + $replace);
        }
        return $text;
    }
}
