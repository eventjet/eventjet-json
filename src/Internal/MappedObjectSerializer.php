<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\DecodeFailure;
use ReflectionClass;
use ReflectionException;

use function get_object_vars;

/** @internal */
final class MappedObjectSerializer
{
    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function serialize(object $object): object
    {
        $class = new ReflectionClass($object);
        try {
            $names = TargetClass::fieldNames($class);
        } catch (DecodeFailure $error) {
            throw $error->whileCreating($class->getName())->toError();
        }
        $result = [];
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = get_object_vars($object);
        foreach ($properties as $name => $value) {
            $result[$names[$name] ?? $name] = $value;
        }
        return (object) $result;
    }
}
