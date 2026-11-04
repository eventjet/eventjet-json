<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\OtherMembers;
use JsonSerializable;

/** @internal */
final readonly class CollectorNamedLikeField implements JsonSerializable
{
    use MappedJsonFields;

    /** @param array<JsonValue> $others */
    public function __construct(
        #[Field('others')]
        public string $label,
        #[OtherMembers]
        public array $others = [],
    ) {}
}
