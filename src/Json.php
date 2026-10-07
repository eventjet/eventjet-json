<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ClassFieldTypeValidator;
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
     * @template T
     * @param string $json
     * @param class-string<T&object>|JsonType<T> $class
     * @phpstan-param (T is object ? class-string<T> : JsonType<T>) $class
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
            return self::decodeArray($class, $values);
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
    private static function decodeArray(JsonType $type, mixed $values): mixed
    {
        if (!is_array($values)) {
            return DecodeError::unexpectedRootValue($values, 'array');
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
}
