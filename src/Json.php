<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\ObjectValueConverter;
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
            $reflection = new ReflectionClass($class);

            $typeError = ObjectTypeValidator::validate($reflection, $values);

            if ($typeError !== null) {
                return $typeError;
            }

            $convertedValues = ObjectValueConverter::convert($reflection, $values);

            if ($convertedValues instanceof DecodeError) {
                return $convertedValues;
            }

            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            return new $class(...$convertedValues);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }
}
