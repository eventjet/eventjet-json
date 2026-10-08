<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final readonly class MappedSpecialNames implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        #[Field('0')]
        public string $zero,
        #[Field('')]
        public string $empty,
        #[Field('second')]
        public string $first,
        #[Field('first')]
        public string $second,
        #[Field('same')]
        public string $same = 'unchanged',
    ) {}
}
