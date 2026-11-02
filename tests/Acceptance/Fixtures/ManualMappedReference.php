<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use JsonSerializable;

/** @internal */
final readonly class ManualMappedReference implements JsonSerializable
{
    public function __construct(
        #[Field('$ref')]
        public string $ref,
    ) {}

    #[\Override]
    public function jsonSerialize(): object
    {
        return (object) ['$ref' => $this->ref];
    }
}
