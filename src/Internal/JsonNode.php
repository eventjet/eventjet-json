<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\JsonError;

use function assert;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_validate;
use function strcspn;
use function strspn;
use function substr;

/**
 * @internal
 */
final readonly class JsonNode
{
    /**
     * @param array<array-key, self>|string|int|float|bool|null $value
     */
    public function __construct(
        public array|string|int|float|bool|null $value,
        public bool $object = false,
        public string|null $number = null,
    ) {
    }

    public static function parse(string $json): self
    {
        if (!json_validate($json)) {
            throw JsonError::decodeFailed('JSON decoding failed');
        }
        // Native validation guarantees complete delimiters and escape sequences.
        $position = 0;
        return self::read($json, $position);
    }

    private static function read(string $json, int &$position): self
    {
        $position += strspn($json, " \t\r\n", $position);
        $token = $json[$position];
        if ($token !== '{' && $token !== '[') {
            $start = $position;
            if ($token === '"') {
                $position++;
                while (true) {
                    $position += strcspn($json, '\\"', $position);
                    if ($json[$position++] === '"') {
                        break;
                    }
                    $position++;
                }
            } else {
                $position += strcspn($json, ",]} \t\r\n", $position);
            }
            $text = substr($json, $start, $position - $start);
            /** @var mixed $value */
            $value = json_decode($text);
            assert(is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null);
            return new self($value, number: is_int($value) || is_float($value) ? $text : null);
        }
        $object = $token === '{';
        $end = $object ? '}' : ']';
        $position++;
        $members = [];
        $position += strspn($json, " \t\r\n", $position);
        while ($json[$position] !== $end) {
            if ($object) {
                $key = self::read($json, $position)->value;
                assert(is_string($key));
                $position += strspn($json, " \t\r\n", $position) + 1;
                $members[$key] = self::read($json, $position);
            } else {
                $members[] = self::read($json, $position);
            }
            $position += strspn($json, " \t\r\n", $position);
            if ($json[$position] === ',') {
                $position++;
                $position += strspn($json, " \t\r\n", $position);
            }
        }
        $position++;
        return new self($members, $object);
    }

    /** @return array<array-key, mixed>|object|string|int|float|bool|null */
    public function native(): array|object|string|int|float|bool|null
    {
        if (!is_array($this->value)) {
            return $this->value;
        }
        $members = [];
        foreach ($this->value as $key => $node) {
            $members[$key] = $node->native();
        }
        return $this->object ? (object)$members : $members;
    }
}
