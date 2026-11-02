<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

final class Deleted implements JsonSerializable
{
    use MappedJsonFields;

    /**
     * @param 'deleted' $type
     * @param 'v2' $schema
     */
    public function __construct(
        #[Field('@type')]
        public string $type,
        public string $schema,
        public string $id,
    ) {}
}
