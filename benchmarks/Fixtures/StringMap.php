<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

/** @api Hydrated dynamically by the benchmark. */
final readonly class StringMap
{
    /** @param non-empty-array<string, string> $values */
    public function __construct(
        public array $values,
    ) {}

    public static function thousandEntries(): self
    {
        $values = ['key-0' => 'value-0'];
        for ($index = 1; $index < 1000; ++$index) {
            $values['key-' . $index] = 'value-' . $index;
        }
        return new self($values);
    }
}
