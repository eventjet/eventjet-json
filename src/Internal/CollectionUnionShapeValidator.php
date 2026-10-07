<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function class_exists;
use function enum_exists;
use function in_array;

/** @internal */
final class CollectionUnionShapeValidator
{
    /** @param class-string $class */
    public static function validate(string $class, string $path, CollectionUnionType $type): DecodeError|null
    {
        $shapes = [];
        foreach ($type->members as $member) {
            $kind = self::kind($member);
            if ($kind === null) {
                continue;
            }
            if (in_array($kind, $shapes, strict: true)) {
                return DecodeError::nonInstantiableTarget(
                    $class,
                    'Field '
                    . $path
                    . ' has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                );
            }
            $shapes[] = $kind;
        }
        return null;
    }

    private static function kind(string|NestedCollectionType $member): string|null
    {
        if ($member instanceof NestedCollectionType) {
            return $member->collection instanceof MapType ? 'object' : 'array';
        }
        return class_exists($member) && !enum_exists($member) ? 'object' : null;
    }
}
