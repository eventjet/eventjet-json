<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity;

use Eventjet\Json\Internal\PhpDocLiteralNumber;
use Eventjet\Json\Internal\PhpDocNumbers;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Test\Parity\Reference\PhpStanNumber;

use function count;
use function filter_var;
use function in_array;
use function is_finite;
use function is_float;
use function is_int;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function var_export;

use const FILTER_VALIDATE_INT;

/**
 * Checks the number literals of a type. The decoder deliberately rejects the literals that PHP and PHPStan read
 * differently, as documented in docs/types.md. Every other literal must have the value that PHPStan gives it.
 *
 * @internal
 */
final class NumberLiterals
{
    /** Returns why the decoder deliberately rejects a literal, or null if it must read it like PHPStan. */
    public static function rejection(PhpStanNumber $number): string|null
    {
        $unsigned = ltrim($number->literal, characters: '+-');
        $prefixed = preg_match('/\A0[box]/i', $unsigned) === 1;
        $leadingZero = preg_match('/\A0[0-9]/', $unsigned) === 1;
        $inRange = filter_var($number->literal, FILTER_VALIDATE_INT) !== false;
        return match (true) {
            $prefixed => 'a binary, octal, or hexadecimal integer',
            is_int($number->value) && $leadingZero => 'an integer with a leading zero',
            is_int($number->value) && !$inRange => 'an integer beyond PHP\'s range',
            is_float($number->value) && !is_finite($number->value) => 'a float that does not fit',
            $number->bound && is_float($number->value) => 'a range bound that is not an integer',
            $number->bound && str_starts_with($number->literal, '+') => 'a range bound with a plus sign',
            default => null,
        };
    }

    /**
     * Returns the differences between how the decoder reads the number literals of a type and how PHPStan reads them,
     * apart from the deliberate rejections. The second value counts the deliberate rejections.
     *
     * @param list<PhpStanNumber> $numbers The literals that PHPStan reads in the type.
     * @return array{list<string>, int}
     */
    public static function differences(PhpDocType $type, array $numbers): array
    {
        $differences = [];
        $rejected = [];
        foreach ($numbers as $number) {
            $reason = self::rejection($number);
            $accepted = !$number->bound || PhpDocLiteralNumber::isRangeBound($number->literal);
            $value = $accepted ? PhpDocLiteralNumber::parse($number->literal) : null;
            if ($reason !== null) {
                $rejected[] = $number->literal;
            }
            $expected = $reason === null ? $number->value : null;
            if ($value !== $expected) {
                $differences[] = sprintf(
                    'number %s%s: PHPStan %s, decoder %s, expected %s',
                    $number->literal,
                    $number->bound ? ' as a range bound' : '',
                    var_export($number->value, return: true),
                    var_export($value, return: true),
                    $reason === null ? var_export($expected, return: true) : 'a rejection of ' . $reason,
                );
            }
        }
        $unsupported = PhpDocNumbers::unsupported($type);
        $consistent = $unsupported === null ? $rejected === [] : in_array($unsupported, $rejected, strict: true);
        if (!$consistent) {
            $differences[] = sprintf(
                'the decoder finds the unsupported number %s, where the rejected numbers are %s',
                var_export($unsupported, return: true),
                TypeDescription::json($rejected),
            );
        }
        return [$differences, count($rejected)];
    }
}
