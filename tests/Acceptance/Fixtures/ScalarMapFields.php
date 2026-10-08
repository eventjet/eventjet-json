<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ScalarMapFields
{
    /** @var non-empty-array<string, string> */
    public array $publicStrings = ['default' => ''];

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
    ) {}
}
