<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use function count;
use function sprintf;

/**
 * Counts the inputs of a parity run and keeps the first differences.
 *
 * @internal
 */
final class ParityReport
{
    private const int SHOWN = 20;

    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<array{string, list<string>}> */
    private array $differences = [];

    private int $differing = 0;

    public function count(string $what, int $by = 1): void
    {
        $this->counts[$what] = ($this->counts[$what] ?? 0) + $by;
    }

    /** @param list<string> $differences */
    public function compare(string $input, array $differences): void
    {
        $this->count('compared');
        if ($differences === []) {
            return;
        }
        $this->differing++;
        if (count($this->differences) < self::SHOWN) {
            $this->differences[] = [$input, $differences];
        }
    }

    public function compared(): int
    {
        return $this->counts['compared'] ?? 0;
    }

    /**
     * Returns the inputs that differ, the first ones with what differs, and a summary of the counts.
     *
     * @return list<string>
     */
    public function differences(): array
    {
        if ($this->differing === 0) {
            return [];
        }
        $lines = [sprintf(
            '%d of %d inputs differ. Counts: %s',
            $this->differing,
            $this->compared(),
            TypeDescription::json($this->counts),
        )];
        foreach ($this->differences as [$input, $differences]) {
            $lines[] = 'Input: ' . TypeDescription::json($input);
            foreach ($differences as $difference) {
                $lines[] = '  ' . $difference;
            }
        }
        return $lines;
    }
}
