<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;

use function get_object_vars;

/** @internal */
final class MappedObjectSerializer
{
    /** @var array<class-string, array<string, string>> */
    private static array $names = [];

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function serialize(object $object): object
    {
        $class = $object::class;
        $names = self::$names[$class] ?? null;
        if ($names === null) {
            $names = FieldNames::resolve(new ReflectionClass($object));
            if ($names instanceof DecodeError) {
                throw $names;
            }
            self::$names[$class] = $names;
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
