<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;

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
        ReflectionParameter|ReflectionProperty $field,
        string $expectedType,
        mixed $value,
    ): array|DecodeError {
        if (!is_array($value) || !array_is_list($value)) {
            return DecodeError::fieldTypeMismatch($class, $field->getName(), $expectedType, $value);
        }

        $isNonEmpty = CollectionTypeResolver::isNonEmptyList($field);

        if ($isNonEmpty && $value === []) {
            return DecodeError::nonInstantiableTarget($class, sprintf(
                'Field %s must be of type %s, empty list given.',
                $field->getName(),
                $expectedType,
            ));
        }

        return $value;
    }
}
