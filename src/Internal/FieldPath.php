<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use JsonException;

use function is_int;
use function json_encode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class FieldPath
{
    /**
     * Returns the path segment of a list index or map key.
     *
     * @throws JsonException
     */
    public static function key(int|string $key): string
    {
        $simple = is_int($key) || preg_match('/^[a-zA-Z0-9_-]+$/D', $key) === 1;
        $name = $simple ? $key : json_encode($key, JSON_THROW_ON_ERROR);

        return '[' . $name . ']';
    }
}
