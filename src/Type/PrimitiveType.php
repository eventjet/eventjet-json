<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use InvalidArgumentException;

use function in_array;

final readonly class PrimitiveType implements ParsedType
{
    private function __construct(
        public string $name,
    ) {
    }

    public static function string(): self
    {
        return new self('string');
    }

    public static function int(): self
    {
        return new self('int');
    }

    public static function float(): self
    {
        return new self('float');
    }

    public static function bool(): self
    {
        return new self('bool');
    }

    public static function null(): self
    {
        return new self('null');
    }

    public static function fromName(string $name): self
    {
        return match ($name) {
            'string' => self::string(),
            'int', 'integer' => self::int(),
            'float', 'double' => self::float(),
            'bool', 'boolean' => self::bool(),
            'null' => self::null(),
            default => throw new InvalidArgumentException("Unknown primitive type: {$name}"),
        };
    }

    public static function isPrimitive(string $name): bool
    {
        return in_array($name, ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'null'], true);
    }
}
