<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

/** @api Hydrated dynamically by the benchmark. */
final readonly class ScalarLists
{
    /**
     * @param list<string> $strings
     * @param list<int> $integers
     * @param list<float> $floats
     * @param list<bool> $booleans
     */
    public function __construct(
        public array $strings,
        public array $integers,
        public array $floats,
        public array $booleans,
    ) {}
}
