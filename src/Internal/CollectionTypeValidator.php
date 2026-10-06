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
            $isNonBackedEnum = enum_exists($type) && !new ReflectionEnum($type)->isBacked();

            if ($isNonBackedEnum) {
                return DecodeError::nonBackedEnum($class, $type, $path);
            }

            $error = ClassFieldTypeValidator::validate($class, $path, $type);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }
}
