<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Performance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\OtherMembers;
use JsonSerializable;

/** @api Hydrated dynamically by the benchmark. */
final readonly class CollectedMembers implements JsonSerializable
{
    use MappedJsonFields;

    /** @param array<array-key, JsonValue> $others */
    public function __construct(
        #[Field('uri')]
        public string $ref = 'example',
        #[Field('total')]
        public int $count = 42,
        #[Field('valid')]
        public bool $ready = true,
        #[OtherMembers]
        public array $others = [],
    ) {}
}
