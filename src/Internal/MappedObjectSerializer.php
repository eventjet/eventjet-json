<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

use function array_filter;
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
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = get_object_vars($object);
        foreach (NullableJsonValues::of($class) as $name => $_) {
            if (($properties[$name] ?? null) !== null) {
                continue;
            }
            unset($properties[$name]);
        }
        return self::write($class, $properties);
    }

    /**
     * Like serialize(), but leaves out every property that is null, for classes in which null means absent.
     *
     * @throws DecodeError
     * @throws ReflectionException
     * @throws UnexpectedValueException If a collected member has the JSON name of a declared field.
     */
    public static function serializeWithoutNulls(object $object): object
    {
        /** @var array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties */
        $properties = array_filter(get_object_vars($object), static fn(mixed $value): bool => $value !== null);
        return self::write(new ReflectionClass($object), $properties);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param array<string, array<array-key, mixed>|object|bool|int|float|string|resource|null> $properties
     * @throws DecodeError
     * @throws UnexpectedValueException
     */
    private static function write(ReflectionClass $class, array $properties): object
    {
        try {
            [$names, , $others] = TargetClass::mapping($class);
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
        $result = [];
        $members = [];
        if ($others !== null) {
            $collecting = $others->property;
            /** @var array<array-key, mixed> $members */
            $members = $properties[$collecting] ?? [];
            unset($properties[$collecting]);
            foreach ($members as $member => $_) {
                if (array_key_exists($member, $others->declared)) {
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
            $result[$names[$name] ?? $name] = $value;
        }
        return (object) ($result + $members);
    }
}
