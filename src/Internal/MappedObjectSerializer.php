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
            [$names, , $others] = TargetClass::mapping($class);
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
        $nullableJsonValues = NullableJsonValues::of($class);
        $result = [];
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = get_object_vars($object);
        $members = [];
        if ($others !== null) {
            [$collecting, $declared] = $others;
            /** @var array<array-key, mixed> $members */
            $members = $properties[$collecting] ?? [];
            unset($properties[$collecting]);
            foreach ($members as $member => $_) {
                if (array_key_exists($member, $declared)) {
                    throw new UnexpectedValueException(sprintf(
                        'Cannot encode %s, because its property %s holds a member named "%s", which is the JSON name of a declared field.',
                        $class->getName(),
                        $collecting,
                        $member,
                    ));
                }
            }
        }
        foreach ($properties as $name => $value) {
            if ($value === null && array_key_exists($name, $nullableJsonValues)) {
                continue;
            }
            $result[$names[$name] ?? $name] = $value;
        }
        return (object) ($result + $members);
    }
}
