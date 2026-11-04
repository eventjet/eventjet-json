<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\MappedObjectSerializer;
use ReflectionException;
use UnexpectedValueException;

/** @api */
trait MappedJsonFields
{
    /**
     * @throws DecodeError
     * @throws ReflectionException
     * @throws UnexpectedValueException If a member that the property marked with #[OtherMembers] holds has the JSON
     *     name of a declared field.
     */
    public function jsonSerialize(): object
    {
        return MappedObjectSerializer::serialize($this);
    }
}
