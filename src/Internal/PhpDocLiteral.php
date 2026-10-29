<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;

use function is_finite;
use function is_numeric;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function strtr;
use function substr;

use const INF;

/**
 * A literal value in PHPDoc, such as `'draft'`, `42`, `true`, or a backed enum case. The name spells the literal in
 * union types and error messages.
 *
 * Only plain decimal numbers are literals, because PHP and PHPStan read others differently: PHPStan casts `0x10` and
 * `052` to 0 and 52, clamps integers beyond PHP's range, and reads `1e999` as infinity.
 *
 * @internal
 */
final readonly class PhpDocLiteral
{
    private const string DECIMAL_INTEGER = '/\A[+-]?(?:0|[1-9][0-9]*+)\z/';

    public function __construct(
        public string|int|float|bool|BackedEnum $value,
        public string $name,
    ) {}

    /** @param 'true'|'false' $name */
    public static function boolean(string $name): self
    {
        return new self($name === 'true', $name);
    }

    /** Reads a quoted string token. */
    public static function quoted(string $quoted): self
    {
        return self::string(PhpDocLiteralString::unescape($quoted), self::quote($quoted));
    }

    /** Reads a quoted string token that has no escapes. */
    public static function quotedWithoutEscapes(string $quoted): self
    {
        return self::string(substr($quoted, offset: 1, length: -1), self::quote($quoted));
    }

    /**
     * Returns a string literal, named with the given quote. Only backslashes and the quote are escaped.
     *
     * @param "'"|'"' $quote
     */
    public static function string(string $value, string $quote = "'"): self
    {
        return new self($value, $quote . strtr($value, ['\\' => '\\\\', $quote => '\\' . $quote]) . $quote);
    }

    /** @return "'"|'"' */
    private static function quote(string $quoted): string
    {
        return str_starts_with($quoted, '"') ? '"' : "'";
    }

    /** Reads an integer token without digit separators. Returns null unless it is a decimal integer in PHP's range. */
    public static function integer(string $number): self|null
    {
        $value = (int) $number;
        // The cast clamps integers beyond PHP's range, whose digits then differ.
        $decimal =
            preg_match(self::DECIMAL_INTEGER, $number) === 1
            && ltrim((string) $value, characters: '-') === ltrim($number, characters: '+-');
        return $decimal ? new self($value, $number) : null;
    }

    /** Reads a float token without digit separators. Returns null unless it is a finite number. */
    public static function float(string $number): self|null
    {
        $value = is_numeric($number) ? (float) $number : INF;
        return is_finite($value) ? new self($value, $number) : null;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
