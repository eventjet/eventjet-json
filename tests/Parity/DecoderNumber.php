<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Test\Parity\Reference\PhpStanNumber;

use function is_float;
use function is_int;
use function str_starts_with;

/**
 * Reads a number literal that PHPStan found the way the decoder reads it.
 *
 * @internal
 */
final class DecoderNumber
{
    /** Returns the value that the decoder reads for a literal, or null if it rejects it. */
    public static function value(PhpStanNumber $number): int|float|null
    {
        $literal = is_int($number->value)
            ? PhpDocLiteral::integer($number->literal)
            : PhpDocLiteral::float($number->literal);
        $value = $literal?->value;
        if (!is_int($value) && !is_float($value)) {
            return null;
        }
        $bound = is_int($value) && !str_starts_with($number->literal, '+');
        return !$number->bound || $bound ? $value : null;
    }
}
