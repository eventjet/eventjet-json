<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\FieldMapping;
use stdClass;

trait MappedJsonFields
{
    public function jsonSerialize(): stdClass
    {
        return FieldMapping::serialize($this);
    }
}
