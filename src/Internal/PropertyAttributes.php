<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Field;
use Eventjet\Json\OtherMembers;
use ReflectionClass;
use ReflectionProperty;

use function array_push;

/**
 * Reads which properties of a class declare attributes, once per class. A field whose property declares none skips
 * looking up #[Discriminator], which would cost a call per field on every first decode.
 *
 * @internal
 */
final class PropertyAttributes
{
    /**
     * Returns the properties that declare any attribute, keyed by name, and the properties that declare #[Field] or
     * #[OtherMembers], including private ancestor properties.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param list<ReflectionProperty> $properties The properties of the class.
     * @return array{array<string, true>, list<ReflectionProperty>}
     */
    public static function of(ReflectionClass $class, array $properties): array
    {
        $attributed = [];
        $annotated = [];
        foreach ([...$properties, ...self::privateAncestorProperties($class)] as $property) {
            if ($property->getAttributes() === []) {
                continue;
            }
            $attributed[$property->getName()] = true;
            if ($property->getAttributes(Field::class) !== [] || $property->getAttributes(OtherMembers::class) !== []) {
                $annotated[] = $property;
            }
        }
        return [$attributed, $annotated];
    }

    /**
     * Returns the private properties of all ancestors, which reflection leaves out of the properties of the class.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return list<ReflectionProperty>
     */
    private static function privateAncestorProperties(ReflectionClass $class): array
    {
        $properties = [];
        $parent = $class->getParentClass();
        while ($parent !== false) {
            array_push($properties, ...$parent->getProperties(ReflectionProperty::IS_PRIVATE));
            $parent = $parent->getParentClass();
        }
        return $properties;
    }
}
