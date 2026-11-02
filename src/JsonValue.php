<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ArrayObject;
use Eventjet\Json\Internal\CanonicalJson;
use InvalidArgumentException;
use JsonSerializable;
use Override;

/**
 * Any JSON value, for fields that hold free-form JSON. It stores what a json_encode()/json_decode() round trip of its
 * input gives, so `1.0` becomes `1` and `-0.0` becomes `0`, and returns one level at a time: nested arrays and objects
 * hold JsonValue objects again.
 *
 * @api
 */
final readonly class JsonValue implements JsonSerializable
{
    /** @var bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null */
    private bool|float|int|string|array|ArrayObject|null $value;

    /** How many levels of arrays and objects the value has. */
    private int $height;

    /**
     * Accepts null, scalars, lists, associative arrays, stdClass, ArrayObject, JsonSerializable objects and backed
     * enums, nested in any combination.
     *
     * @throws InvalidArgumentException For input that isn't JSON-shaped, such as a closure, a float that isn't finite,
     *     a string that isn't valid UTF-8, a member name that starts with a null byte, or input nested more than 512
     *     levels deep. Also if a jsonSerialize() method throws, with its exception as the previous one.
     */
    public function __construct(mixed $value)
    {
        if ($value instanceof self) {
            $this->value = $value->value;
            $this->height = $value->height;
            return;
        }
        [$this->value, $this->height] = CanonicalJson::of($value);
    }

    /**
     * Returns the value. A JSON array is a list and a JSON object is an ArrayObject; changing either doesn't change
     * this value.
     *
     * @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null
     */
    public function value(): bool|float|int|string|array|ArrayObject|null
    {
        return $this->value instanceof ArrayObject ? clone $this->value : $this->value;
    }

    /** @return bool|float|int|string|list<JsonValue>|ArrayObject<array-key, JsonValue>|null */
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->value();
    }
}
