<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\OtherMembers;
use JsonSerializable;

/** @internal */
final readonly class CollectedMembers implements JsonSerializable
{
    use MappedJsonFields;

    /** @param array<array-key, JsonValue> $others */
    public function __construct(
        #[Field('$id')]
        public string $id,
        public JsonValue|null $extra = null,
        public int|null $count = null,
        #[OtherMembers]
        public array $others = [],
    ) {}
}
