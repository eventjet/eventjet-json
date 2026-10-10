<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;

use function enum_exists;
use function sprintf;

/** @internal */
final class CollectionTypeValidator
{
    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(
        string $class,
        string $field,
        ListType|MapType|TupleType $collection,
    ): DecodeError|null {
        $types = match (true) {
            $collection instanceof ListType => [$collection->itemType],
            $collection instanceof MapType => [$collection->valueType],
            default => $collection->types,
        };

        foreach ($types as $index => $type) {
            $path = $collection instanceof TupleType ? sprintf('%s[%d]', $field, $index) : $field;
            $error = match (true) {
                $type instanceof NestedCollectionType => self::validate($class, $path, $type->collection),
                $type instanceof CollectionUnionType => CollectionUnionTypeValidator::validate($class, $path, $type),
                default => self::named($class, $path, $type),
            };
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function named(string $class, string $path, string $type): DecodeError|null
    {
        $isNonBackedEnum = enum_exists($type) && !new ReflectionEnum($type)->isBacked();
        if ($isNonBackedEnum) {
            return DecodeError::nonBackedEnum($class, $type, $path);
        }
        $isNonEncodable = EnumFieldTypes::isNonEncodable($type);
        if ($isNonEncodable) {
            return DecodeError::nonInstantiableField(
                $class,
                $path,
                'non-JSON-encodable type',
                $type,
                ', which has no JSON representation.',
            );
        }
        return ClassFieldTypeValidator::validate($class, $path, $type);
    }
}
