<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ObjectHydrator;
use stdClass;

use function json_decode;
use function json_last_error;
use function json_last_error_msg;

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
        $values = json_decode($json);

        if ($values === null && json_last_error() !== JSON_ERROR_NONE) {
            return DecodeError::invalidJson(json_last_error_msg());
        }

        if (!$values instanceof stdClass) {
            return DecodeError::unexpectedRootValue($values);
        }

        return ObjectHydrator::hydrate($class, $values);
    }
}
