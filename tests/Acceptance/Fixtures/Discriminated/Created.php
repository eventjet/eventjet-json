<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures\Discriminated;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

final class Created implements JsonSerializable
{
    use MappedJsonFields;

    /**
     * @param 'created' $type
     * @param 'v1' $schema
     */
    public function __construct(
        #[Field('@type')]
        public string $type,
        public string $schema,
        public string $id,
    ) {}
}
