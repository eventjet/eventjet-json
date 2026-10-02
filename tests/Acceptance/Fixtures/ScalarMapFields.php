<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use function array_map;
use function get_debug_type;

final class ScalarMapFields
{
    /** @var array<array-key, string> */
    public array $publicStrings = [];

    /** @var array<array-key, string> */
    private array $floatValueTypes;

    /**
     * @param array<array-key, string> $strings
     * @param array<array-key, int> $integers
     * @param array<array-key, float> $floats
     * @param array<array-key, bool> $booleans
     */
    public function __construct(
        public array $strings,
        public array $integers,
        public array $floats,
        public array $booleans,
    ) {
        $this->floatValueTypes = array_map(static fn(mixed $value): string => get_debug_type($value), $floats);
    }

    /** @return array<array-key, string> */
    public function floatValueTypes(): array
    {
        return $this->floatValueTypes;
    }
}
