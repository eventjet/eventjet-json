<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

use JsonException;
use stdClass;

use function assert;
use function json_decode;
use function json_encode;

use const JSON_FORCE_OBJECT;
use const JSON_THROW_ON_ERROR;

/** @internal */
final class JsonObject
{
    /**
     * Returns the object that json_decode() produces for the given scalar members.
     *
     * @param array<array-key, bool|float|int|string|null> $members
     * @throws JsonException
     */
    public static function of(array $members): stdClass
    {
        /** @var mixed $object */
        $object = json_decode(
            json_encode($members, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
            flags: JSON_THROW_ON_ERROR,
        );
        assert($object instanceof stdClass, description: 'JSON_FORCE_OBJECT encodes a JSON object.');
        return $object;
    }
}
