<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use ArrayObject;

final class Drawing
{
    public Circle|Rectangle|null $background = null;

    /** @var list<Circle|Rectangle> */
    public array $drafts = [];

    /**
     * @param list<Circle|Rectangle> $shapes
     * @param ArrayObject<string, Circle|Rectangle> $named
     * @param non-empty-array<string, Circle|Rectangle> $layers
     * @param array{Circle|Rectangle, int} $pinned
     */
    public function __construct(
        public Circle|Rectangle $main,
        public array $shapes,
        public ArrayObject $named,
        public array $layers,
        public array $pinned,
    ) {}
}
