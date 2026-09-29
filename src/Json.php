<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\JsonDecoder;
use Eventjet\Json\Internal\JsonEncoder;
use Eventjet\Json\Internal\JsonNode;
use Eventjet\Json\Internal\PhpType;

use function class_exists;
use function is_object;

final class Json
{
    public static function encode(mixed $value): string
    {
        return JsonEncoder::encode($value);
    }

    /**
     * @template T of object
     * @param T|class-string<T>|string $value
     * @return ($value is class-string<T> ? T : ($value is T ? T : mixed))
     */
    public static function decode(string $json, object|string $value): mixed
    {
        return self::decodeTarget($json, $value);
    }

    /** @return array<array-key, mixed>|object|string|int|float|bool|null */
    private static function decodeTarget(string $json, object|string $value): array|object|string|int|float|bool|null
    {
        $node = JsonNode::parse($json);
        if (is_object($value)) {
            JsonDecoder::populate($node, $value);
            return $value;
        }
        if (class_exists($value)) {
            return JsonDecoder::object($node, $value);
        }
        return JsonDecoder::value($node, PhpType::target($value), 'root value');
    }
}
