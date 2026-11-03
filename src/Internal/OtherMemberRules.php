<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidOtherMembers;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

use function in_array;

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
     * @param list<ReflectionProperty> $named The properties that declare #[Field], including private ancestor
     *     properties.
     * @throws DecodeError
     */
    public static function resolve(ReflectionClass $class, array $collecting, array $named): string
    {
        $property = $collecting[0];
        $name = $property->getName();
        $second = $collecting[1] ?? null;
        if ($second !== null) {
            throw InvalidOtherMembers::repeated($name, $second->getName());
        }
        $publicInstance = FieldNameRules::isPublicInstance($property);
        if (!$publicInstance) {
            throw InvalidOtherMembers::nonPublicProperty($name);
        }
        if (in_array($property, $named, strict: true)) {
            throw InvalidOtherMembers::withField($name);
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            throw InvalidOtherMembers::withoutJsonSerializable();
        }
        return $name;
    }
}
