<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\Error\InvalidFieldMapping;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\OtherMembers;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_values;
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
     * @param array<ReflectionProperty> $annotated The properties that declare #[Field] or #[OtherMembers], including
     *     private ancestor properties.
     * @return array{array<string, string>, string|null, array<array-key, true>} JSON names keyed by property name, the
     *     property that collects the other members, if any, and the JSON names of the other public instance properties
     *     as keys if it exists.
     * @throws DecodeError
     */
    public static function resolve(ReflectionClass $class, array $properties, array $annotated): array
    {
        $collecting = array_values(array_filter(
            $annotated,
            static fn(ReflectionProperty $property): bool => $property->getAttributes(OtherMembers::class) !== [],
        ));
        $others = $collecting === [] ? null : OtherMemberRules::resolve($class, $collecting);
        try {
            $names = self::read(array_filter(
                $annotated,
                static fn(ReflectionProperty $property): bool => $property->getAttributes(Field::class) !== [],
            ));
        } catch (DecodeError $error) {
            throw $error;
        } catch (Throwable $error) {
            throw UnsupportedTarget::unreadable($error);
        }
        if (!$class->implementsInterface(JsonSerializable::class)) {
            throw InvalidFieldMapping::withoutJsonSerializable();
        }
        self::rejectCollisions($properties, $names, $others);
        return [$names, $others, $others === null ? [] : OtherMemberRules::declared($properties, $names, $others)];
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
     * Rejects two public instance properties that use the same JSON name. The property that collects the other members
     * has none.
     *
     * @param list<ReflectionProperty> $properties
     * @param array<string, string> $names
     * @throws DecodeError
     */
    private static function rejectCollisions(array $properties, array $names, string|null $others): void
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
    }

    private static function isPublicInstance(ReflectionProperty $property): bool
    {
        return (
            ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
            === ReflectionProperty::IS_PUBLIC
        );
    }
}
