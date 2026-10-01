<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar;

use JsonException;
use stdClass;
use UnexpectedValueException;

use function array_map;
use function get_object_vars;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class CanonicalJson
{
    /**
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    public static function canonicalize(string $json): string
    {
        return json_encode(
            self::normalize(json_decode($json, flags: JSON_THROW_ON_ERROR)),
            JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return object|array<array-key, mixed>|string|int|float|bool|null
     * @throws UnexpectedValueException
     */
    private static function normalize(mixed $value): object|array|string|int|float|bool|null
    {
        if ($value instanceof stdClass) {
            return self::normalizeObject($value);
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        throw new UnexpectedValueException('json_decode returned a value outside the JSON data model.');
    }

    /** @throws UnexpectedValueException */
    private static function normalizeObject(stdClass $value): object
    {
        $properties = get_object_vars($value);
        ksort($properties);

        return (object) array_map(self::normalize(...), $properties);
    }
}
