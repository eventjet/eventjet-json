<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\MapType;
use Eventjet\Json\Internal\NestedCollectionType;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\RootTypeValidator;
use ReflectionClass;
use stdClass;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;

use const JSON_ERROR_NONE;

final class Json
{
    /**
     * Check declarations, including nested classes, without constructing objects.
     *
     * @param class-string|JsonType<mixed> $type
     */
    public static function validateType(string|JsonType $type): DecodeError|null
    {
        $class = is_string($type) ? $type : $type->itemClass();
        try {
            if (!is_string($type)) {
                $error = ClassFieldTypeValidator::validate($class, '[]', $class);
                if ($error !== null) {
                    return $error;
                }
            }
            return new ClassGraphValidator()->validate($class);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @template T
     * @param string $json
     * @param class-string<T&object>|JsonType<T> $class
     * @phpstan-param (T is object ? class-string<T> : never)|JsonType<T> $class
     * @psalm-param class-string<T&object>|JsonType<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $json, string|JsonType $class): mixed
    {
        /** @var mixed $values */
        $values = json_decode($json);

        if ($values === null && json_last_error() !== JSON_ERROR_NONE) {
            return DecodeError::invalidJson(json_last_error_msg());
        }

        if (!is_string($class)) {
            return self::decodeCollection($class, $values);
        }

        if (!$values instanceof stdClass) {
            return DecodeError::unexpectedRootValue($values);
        }

        return ObjectHydrator::hydrate($class, $values);
    }

    /**
     * @template T
     * @param JsonType<T> $type
     * @return T|DecodeError
     */
    private static function decodeCollection(JsonType $type, mixed $values): mixed
    {
        $rootType = self::rootType($type);
        $matchesRoot = match ($rootType) {
            'object' => $values instanceof stdClass,
            'array' => is_array($values),
        };
        if (!$matchesRoot) {
            return DecodeError::unexpectedRootValue($values, $rootType);
        }

        $class = $type->itemClass();

        try {
            $error = ClassFieldTypeValidator::validate($class, '[]', $class) ?? RootTypeValidator::validate(
                new ReflectionClass($class),
            );

            if ($error !== null) {
                return $error;
            }

            return $type->decodeValue($values);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @param JsonType<mixed> $type
     * @return 'array'|'object'
     */
    private static function rootType(JsonType $type): string
    {
        $collection = $type->collectionItem();
        return $collection instanceof NestedCollectionType && $collection->collection instanceof MapType
            ? 'object'
            : 'array';
    }
}
