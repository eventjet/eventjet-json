<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final readonly class MappedBracketReference implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        #[Field('[0]')]
        public string $ref,
    ) {}
}
