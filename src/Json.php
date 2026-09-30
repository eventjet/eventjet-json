<?php

declare(strict_types=1);

namespace Eventjet\Json;

use JsonException;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class Json
{
    /**
     * @template T of object
     * @param string $json
     * @param class-string<T> $class
     * @return T
     * @throws JsonException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public static function decode(string $json, string $class): object
    {
        /** @var mixed $values */
        $values = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($values)) {
            throw new UnexpectedValueException('Expected a JSON object.');
        }

        /** @mago-expect analysis:invalid-return-statement Mago models this as nullable even though null is only returned while throwing. */
        return new ReflectionClass($class)->newInstanceArgs($values);
    }
}
