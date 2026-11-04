<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;
use Eventjet\Json\JsonValue;

/** @internal */
final readonly class JsonValueFields
{
    /**
     * @param list<JsonValue> $items
     * @param ArrayObject<string, JsonValue> $members
     * @param non-empty-array<string, JsonValue> $nonEmpty
     * @param array{0: JsonValue, 1?: JsonValue} $tuple
     */
    public function __construct(
        public JsonValue $value,
        public array $items,
        public ArrayObject $members,
        public array $nonEmpty,
        public array $tuple,
    ) {}
}
