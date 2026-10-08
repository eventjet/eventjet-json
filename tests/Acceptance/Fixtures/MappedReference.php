<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final readonly class MappedReference implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        #[Field('$ref')]
        public string $ref,
    ) {}
}
