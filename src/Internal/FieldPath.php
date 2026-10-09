<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use JsonException;

use function json_encode;
use function strlen;
use function strspn;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class FieldPath
{
    private const string SIMPLE_KEY_BYTES = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';

    public static function field(string $parent, string $name): string
    {
        return $parent === '' ? $name : $parent . '.' . $name;
    }

    /** @throws JsonException */
    public static function key(string $parent, string $key): string
    {
        $simple = $key !== '' && strspn($key, self::SIMPLE_KEY_BYTES) === strlen($key);
        $name = $simple ? $key : json_encode($key, JSON_THROW_ON_ERROR);

        return $parent . '[' . $name . ']';
    }
}
