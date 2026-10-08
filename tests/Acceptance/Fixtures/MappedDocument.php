<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final readonly class MappedDocument implements JsonSerializable
{
    use MappedJsonFields;

    /** @param list<MappedReference> $references */
    public function __construct(
        #[Field('$id')]
        public string $id,
        #[Field('links')]
        public array $references,
        #[Field('target')]
        public MappedReference|string|null $reference = null,
    ) {}
}
