<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;

use function get_object_vars;
use function in_array;

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
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
        $nullableJsonValues = NullableJsonValues::of($class);
        $result = [];
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = get_object_vars($object);
        foreach ($properties as $name => $value) {
            if ($value === null && in_array($name, $nullableJsonValues, strict: true)) {
                continue;
            }
            $result[$names[$name] ?? $name] = $value;
        }
        return (object) $result;
    }
}
