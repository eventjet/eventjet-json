<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final readonly class MappedDefaults implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        public string $title = 'default',
        #[Field('$ref')]
        public string|null $ref = 'original',
        public int $count = 7,
    ) {}
}
