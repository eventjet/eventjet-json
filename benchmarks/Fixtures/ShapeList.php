<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Fixtures;

use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Circle;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Rectangle;

/** @api Hydrated dynamically by the benchmark. */
final readonly class ShapeList
{
    /** @param list<Circle|Rectangle> $shapes */
    public function __construct(
        public array $shapes,
    ) {}

    public static function mixed(int $size): self
    {
        $shapes = [];
        for ($index = 0; $index < $size; ++$index) {
            $shapes[] = ($index % 2) === 0 ? new Circle($index / 2) : new Rectangle($index, $index + 1);
        }
        return new self($shapes);
    }
}
