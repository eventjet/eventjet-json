<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final readonly class MappedOrdinaryConstructor implements JsonSerializable
{
    use MappedJsonFields;

    #[Field('$ref')]
    public string $ref;

    public function __construct(string $ref)
    {
        $this->ref = $ref;
    }
}
