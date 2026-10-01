<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ReflectionClass;
use Throwable;

use function is_array;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function ltrim;
use function str_starts_with;

use const JSON_ERROR_NONE;

final class Json
{
    /**
     * @template T of object
     * @param string $json
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $json, string $class): object
    {
        /** @var mixed $values */
        $values = json_decode($json, associative: true);

        if ($values === null && json_last_error() !== JSON_ERROR_NONE) {
            return DecodeError::invalidJson(json_last_error_msg());
        }

        // Associative decoding loses the root shape, so inspect the validated JSON.
        if (!is_array($values) || str_starts_with(ltrim($json), '[')) {
            return DecodeError::unexpectedRootValue($values);
        }

        try {
            /** @mago-expect analysis:invalid-return-statement Mago models this as nullable even though null is only returned while throwing. */
            return new ReflectionClass($class)->newInstanceArgs($values);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }
}
