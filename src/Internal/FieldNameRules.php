<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\InvalidFieldMapping;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function array_filter;
use function array_key_exists;
use function count;

/**
 * Reads the #[Field] names of a class and the property that #[OtherMembers] marks, and checks the rules for them. Kept
 * apart from TargetClass, so that classes without either attribute do not load it.
 *
 * @internal
 */
final class FieldNameRules
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties The properties of the class.
     * @param list<ReflectionProperty> $named The properties that declare #[Field], including private ancestor
     *     properties.
     * @param list<ReflectionProperty> $collecting The properties that declare #[OtherMembers], including private
     *     ancestor properties.
     * @return array{array<string, string>, OtherMembersMapping|null} JSON names keyed by property name, and the
     *     property that collects the other members, if any.
     * @throws DecodeError
     */
    public static function resolve(ReflectionClass $class, array $properties, array $named, array $collecting): array
    {
        // Checked first, so that a class without JsonSerializable gets the message that explains #[OtherMembers].
        $others = $collecting === [] ? null : OtherMemberRules::resolve($class, $collecting, $named);
        try {
            $names = self::read($named);
        } catch (DecodeError $error) {
            throw $error;
        } catch (Throwable $error) {
            throw UnsupportedTarget::unreadable($error);
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            throw InvalidFieldMapping::withoutJsonSerializable();
        }
        $owners = self::rejectCollisions($properties, $names, $others);
        return [$names, $others === null ? null : new OtherMembersMapping($others, $owners)];
    }

    /**
     * @param array<ReflectionProperty> $properties
     * @return array<string, string>
     * @throws DecodeError
     */
    private static function read(array $properties): array
    {
        $names = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Field::class);
            $name = $property->getName();
            $publicInstance = self::isPublicInstance($property);
            if (!$publicInstance) {
                throw InvalidFieldMapping::nonPublicProperty($name);
            }
            if (count($attributes) !== 1) {
                throw InvalidFieldMapping::repeated($name);
            }
            $names[$name] = $attributes[0]->newInstance()->name;
        }
        return $names;
    }

    /**
     * Rejects two public instance properties that use the same JSON name, or returns their names keyed by JSON name.
     * The property that collects the other members has none.
     *
     * @param list<ReflectionProperty> $properties
     * @param array<string, string> $names
     * @return array<array-key, string>
     * @throws DecodeError
     */
    private static function rejectCollisions(array $properties, array $names, string|null $others): array
    {
        $owners = [];
        foreach (array_filter($properties, self::isPublicInstance(...)) as $property) {
            $name = $property->getName();
            if ($name === $others) {
                continue;
            }
            $wireName = $names[$name] ?? $name;
            if (array_key_exists($wireName, $owners)) {
                throw InvalidFieldMapping::collision($owners[$wireName], $name, $wireName);
            }
            $owners[$wireName] = $name;
        }
        return $owners;
    }

    public static function isPublicInstance(ReflectionProperty $property): bool
    {
        return (
            ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
            === ReflectionProperty::IS_PUBLIC
        );
    }
}
