<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
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
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        string $path,
        CollectionUnionType $type,
        mixed $value,
    ): array|bool|float|int|object|string|null {
        $collection = $type->collectionFor($value);
        if ($collection !== null) {
            return CollectionValueConverter::convert($class, $path, $collection, $value);
        }
        $names = $type->names();
        foreach ($type->literalNames as $name) {
            $matchesLiteral = PhpDocLiteral::matches($name, $value);
            if ($matchesLiteral) {
                return PhpDocLiteral::value($name);
            }
        }
        $enum = BackedEnumValueConverter::convertUnion($class, $path, $names, $value);
        if ($enum !== null) {
            return $enum;
        }
        foreach ($names as $member) {
            if ($value instanceof stdClass && !enum_exists($member) && class_exists($member)) {
                return ConcreteClassValueConverter::convertCollectionItem($class, $path, $member, $value);
            }
        }

        $matches =
            in_array(get_debug_type($value), $names, strict: true)
            || $value === true && in_array('true', $names, strict: true)
            || $value === false && in_array('false', $names, strict: true);
        if ($matches) {
            /** @var bool|float|int|string|null $value */
            return $value;
        }
        if (is_int($value) && in_array('float', $names, strict: true)) {
            return (float) $value;
        }
        return DecodeError::fieldTypeMismatch($class, $path, (string) $type, $value);
    }
}
