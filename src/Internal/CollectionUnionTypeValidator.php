<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;

use function class_exists;
use function enum_exists;

/** @internal */
final class CollectionUnionTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, string $path, CollectionUnionType $type): DecodeError|null
    {
        foreach ($type->members as $member) {
            if (!$member instanceof NestedCollectionType) {
                continue;
            }
            $error = CollectionTypeValidator::validate($class, $path, $member->collection);
            if ($error !== null) {
                return $error;
            }
        }
        $names = $type->names();
        $classes = [];
        foreach ($names as $member) {
            $error = CollectionTypeValidator::named($class, $path, $member);
            if ($error !== null) {
                return $error;
            }
            if (!enum_exists($member) && class_exists($member)) {
                $classes[] = $member;
            }
        }
        return (
            ClassUnionValidator::validateNames($class, $path, $classes)
            ?? EnumUnionValidator::validateNames($class, $path, $names)
            ?? CollectionUnionShapeValidator::validate($class, $path, $type)
        );
    }
}
