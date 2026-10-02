<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use function array_map;
use function get_debug_type;

final class ScalarMapFields
{
    /** @var non-empty-array<string, string> */
    public array $publicStrings = ['default' => ''];

    /** @var non-empty-array<string, string> */
    private array $floatValueTypes;

    /**
     * @param non-empty-array<string, string> $strings
     * @param non-empty-array<string, int> $integers
     * @param non-empty-array<string, float> $floats
     * @param non-empty-array<string, bool> $booleans
     */
    public function __construct(
        public array $strings,
        public array $integers,
        public array $floats,
        public array $booleans,
    ) {
        $this->floatValueTypes = array_map(static fn(mixed $value): string => get_debug_type($value), $floats);
    }

    /** @return non-empty-array<string, string> */
    public function floatValueTypes(): array
    {
        return $this->floatValueTypes;
    }
}
