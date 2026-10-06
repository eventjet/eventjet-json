<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use stdClass;

use function class_exists;
use function enum_exists;
use function get_debug_type;
use function in_array;
use function is_int;

/** @internal */
final class CollectionUnionValueConverter
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        string $path,
        CollectionUnionType $type,
        mixed $value,
    ): bool|float|int|object|string|null {
        $enum = BackedEnumValueConverter::convertUnion($class, $path, $type->members, $value);
        if ($enum !== null) {
            return $enum;
        }
        foreach ($type->members as $member) {
            if ($value instanceof stdClass && !enum_exists($member) && class_exists($member)) {
                return ConcreteClassValueConverter::convertCollectionItem($class, $path, $member, $value);
            }
        }

        $matches =
            in_array(get_debug_type($value), $type->members, strict: true)
            || $value === true && in_array('true', $type->members, strict: true)
            || $value === false && in_array('false', $type->members, strict: true);
        if ($matches) {
            /** @var bool|float|int|string|null $value */
            return $value;
        }
        if (is_int($value) && in_array('float', $type->members, strict: true)) {
            return (float) $value;
        }
        return DecodeError::fieldTypeMismatch($class, $path, (string) $type, $value);
    }
}
