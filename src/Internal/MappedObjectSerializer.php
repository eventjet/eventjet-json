<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

use function array_key_exists;
use function get_object_vars;
use function sprintf;

/** @internal */
final class MappedObjectSerializer
{
    /**
     * Writes the public instance properties under their JSON names, then the members that the property marked with
     * #[OtherMembers] collects.
     *
     * @throws DecodeError
     * @throws ReflectionException
     * @throws UnexpectedValueException If a collected member has the JSON name of a declared field, because decoding
     *     would bind it to that field.
     */
    public static function serialize(object $object): object
    {
        $class = new ReflectionClass($object);
        try {
            $mapping = TargetClass::mapping($class);
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
        if ($mapping[2] !== null) {
            return self::collecting($object, $class, $mapping[0], $mapping[2], $mapping[3]);
        }
        $names = $mapping[0];
        $nullableJsonValues = NullableJsonValues::of($class);
        $result = [];
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = get_object_vars($object);
        foreach ($properties as $name => $value) {
            if ($value === null && array_key_exists($name, $nullableJsonValues)) {
                continue;
            }
            $result[$names[$name] ?? $name] = $value;
        }
        return (object) $result;
    }

    /**
     * Serializes an object whose class collects other members.
     *
     * @param ReflectionClass<object> $class
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @param string $others The property that collects the other members.
     * @param array<array-key, true> $declared The JSON names of the other public instance properties, as keys.
     * @throws UnexpectedValueException
     */
    private static function collecting(
        object $object,
        ReflectionClass $class,
        array $names,
        string $others,
        array $declared,
    ): object {
        $nullableJsonValues = NullableJsonValues::of($class);
        $result = [];
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = get_object_vars($object);
        /** @var array<array-key, mixed> $members */
        $members = $properties[$others] ?? [];
        unset($properties[$others]);
        foreach ($properties as $name => $value) {
            if ($value === null && array_key_exists($name, $nullableJsonValues)) {
                continue;
            }
            $result[$names[$name] ?? $name] = $value;
        }
        foreach ($members as $member => $_) {
            if (array_key_exists($member, $declared)) {
                throw new UnexpectedValueException(sprintf(
                    'Cannot encode %s, because its property %s holds a member named "%s", which is the JSON name of a declared field.',
                    $class->getName(),
                    $others,
                    $member,
                ));
            }
        }
        return (object) ($result + $members);
    }
}
