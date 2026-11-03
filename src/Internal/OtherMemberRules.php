<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\InvalidOtherMembers;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

/**
 * Checks the rules for the property that #[OtherMembers] marks. FieldNameRules loads it only for classes that use the
 * attribute. OtherMembersField checks the type of the property.
 *
 * @internal
 */
final class OtherMemberRules
{
    /**
     * Returns the name of the property that collects the other members.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param non-empty-list<ReflectionProperty> $collecting The properties that declare #[OtherMembers], including
     *     private ancestor properties.
     * @throws DecodeError
     */
    public static function resolve(ReflectionClass $class, array $collecting): string
    {
        $property = $collecting[0];
        $name = $property->getName();
        $second = $collecting[1] ?? null;
        if ($second !== null) {
            throw InvalidOtherMembers::repeated($name, $second->getName());
        }
        $publicInstance = self::isPublicInstance($property);
        if (!$publicInstance) {
            throw InvalidOtherMembers::nonPublicProperty($name);
        }
        if ($property->getAttributes(Field::class) !== []) {
            throw InvalidOtherMembers::withField($name);
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            throw InvalidOtherMembers::withoutJsonSerializable();
        }
        return $name;
    }

    /**
     * Returns the JSON names of the public instance properties other than the one that collects the other members, as
     * keys.
     *
     * @param list<ReflectionProperty> $properties The properties of the class.
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @return array<array-key, true>
     */
    public static function declared(array $properties, array $names, string $others): array
    {
        $declared = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $publicInstance = self::isPublicInstance($property);
            if (!$publicInstance || $name === $others) {
                continue;
            }
            $declared[$names[$name] ?? $name] = true;
        }
        return $declared;
    }

    private static function isPublicInstance(ReflectionProperty $property): bool
    {
        return (
            ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
            === ReflectionProperty::IS_PUBLIC
        );
    }
}
