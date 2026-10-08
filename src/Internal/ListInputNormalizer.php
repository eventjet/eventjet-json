<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function array_is_list;
use function is_array;
use function sprintf;

/** @internal */
final class ListInputNormalizer
{
    /**
     * @param class-string $class
     * @return list<mixed>|DecodeError
     */
    public static function normalize(
        string $class,
        string $path,
        string $expectedType,
        ListType $collection,
        mixed $value,
    ): array|DecodeError {
        if (!is_array($value) || !array_is_list($value)) {
            return DecodeError::fieldTypeMismatch($class, $path, $expectedType, $value);
        }

        if ($collection->nonEmpty && $value === []) {
            return DecodeError::nonInstantiableTarget($class, sprintf(
                'Field %s must be of type %s, empty list given.',
                $path,
                $expectedType,
            ));
        }

        return $value;
    }
}
