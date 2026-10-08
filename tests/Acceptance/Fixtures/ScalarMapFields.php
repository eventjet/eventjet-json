<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use function array_map;
use function array_values;
use function get_debug_type;

final class ScalarMapFields
{
    /** @var non-empty-array<string, string> */
    public array $publicStrings = ['default' => ''];

    /**
     * @param non-empty-array<string, string> $strings
     * @param non-empty-array<string, int> $integers
     * @param non-empty-array<string, float> $floats
     * @param non-empty-array<string, bool> $booleans
     * @param list<string> $numberItemTypes
     */
    public function __construct(
        public array $strings,
        public array $integers,
        public array $floats,
        public array $booleans,
        public array $numberItemTypes = [],
    ) {
        $this->numberItemTypes = [
            ...array_values(array_map(static fn(mixed $value): string => get_debug_type($value), $integers)),
            ...array_values(array_map(static fn(mixed $value): string => get_debug_type($value), $floats)),
        ];
    }
}
