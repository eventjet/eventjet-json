<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class RefinedStringCollectionFields
{
    /** @var non-empty-list<numeric-string> */
    public array $numericList = ['0'];

    /** @var ArrayObject<string, non-empty-string> */
    public ArrayObject $labelMap;

    /** @var non-empty-list<literal-string> */
    public array $literalNonEmptyList = [''];

    /** @var ArrayObject<string, literal-string> */
    public ArrayObject $literalObjectMap;

    /**
     * @param list<literal-string> $literalList
     * @param non-empty-array<string, literal-string> $literalMap
     * @param list<non-empty-string> $labels
     * @param non-empty-array<string, numeric-string> $numbers
     */
    public function __construct(
        public array $labels = [],
        public array $numbers = ['default' => '0'],
        public array $literalList = [],
        public array $literalMap = ['default' => ''],
    ) {
        $this->labelMap = new ArrayObject();
        $this->literalObjectMap = new ArrayObject();
    }
}
