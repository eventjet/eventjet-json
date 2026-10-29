<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use function array_map;
use function get_debug_type;

final class ScalarListFields
{
    /**
     * @param list<int> $stringsExtra
     * @param list<string> $strings
     * @param list<float> $floats
     * @param list<bool> $booleans
     * @param list<string> $floatItemTypes
     */
    public function __construct(
        public array $strings,
        public array $stringsExtra,
        public array $floats,
        public array $booleans,
        public array $floatItemTypes = [],
    ) {
        $this->floatItemTypes = array_map(static fn(mixed $value): string => get_debug_type($value), $floats);
    }
}
