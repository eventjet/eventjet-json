<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;
use Stringable;

use function json_decode;
use function json_last_error;
use function sprintf;

use const JSON_ERROR_NONE;

/**
 * @phpstan-type JsonValue array<array-key, mixed> | bool | string | float | int | null
 */
abstract class JsonType implements Stringable
{
    public static function array(self $elementType): Array_
    {
        return new Array_($elementType);
    }

    public static function boolean(): Boolean
    {
        return new Boolean();
    }

    public static function false(): Boolean
    {
        return Boolean::false();
    }

    public static function null(): Null_
    {
        return new Null_();
    }

    public static function number(): Number
    {
        return new Number();
    }

    /**
     * @param array<string, Member> $members
     */
    public static function object(array $members = []): Object_
    {
        return new Object_($members);
    }

    public static function string(): String_
    {
        return new String_();
    }

    public static function true(): Boolean
    {
        return Boolean::true();
    }

    /**
     * @no-named-arguments
     */
    public static function union(self $first, self $second, self ...$other): Union
    {
        return new Union($first, $second, ...$other);
    }

    protected static function joinPath(string $prefix, string|int $key): string
    {
        return $prefix === '' ? (string)$key : sprintf('%s.%d', $prefix, $key);
    }

    /**
     * Returns a string representation of the type in TypeScript syntax.
     */
    #[Override]
    abstract public function __toString(): string;

    public function or(self $other): Union
    {
        return new Union($this, $other);
    }

    final public function validate(string $json): ValidationResult
    {
        /** @var JsonValue $decoded */
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ValidationResult::error('Invalid JSON', '');
        }
        return static::validateValue($decoded, '');
    }

    /**
     * The canonicalize method is used to obtain the canonical form of the type. Two types are considered
     * equal if their canonical forms are equal. This method, when used in conjunction with the __toString()
     * method, allows for comparing types. For instance, the types "string | number" and "number | string"
     * are considered equal because their canonical forms are equal. The method returns the canonical form of
     * the type.
     */
    public function canonicalize(): self
    {
        return $this;
    }

    abstract public function validateValue(mixed $value, string $path = ''): ValidationResult;
}
