<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\MappedObjectSerializer;
use ReflectionException;

/** @api */
trait MappedJsonFields
{
    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function jsonSerialize(): object
    {
        return MappedObjectSerializer::serialize($this);
    }
}
