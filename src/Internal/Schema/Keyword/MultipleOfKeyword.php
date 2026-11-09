<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function is_int;
use function sprintf;
use function sscanf;
use function strlen;
use function var_export;

/** @internal */
final readonly class MultipleOfKeyword implements Keyword
{
    /** The divisor as $rest * 2 ** $twos * 5 ** $fives * 10 ** $exponent, with a $rest that 2 and 5 don't divide. */
    private int $rest;
    private int $twos;
    private int $fives;
    private int $exponent;

    public function __construct(
        private int|float $divisor,
        private string $location,
    ) {
        [$mantissa, $this->exponent] = self::decimal($divisor);
        [$this->rest, $this->twos, $this->fives] = self::factors($mantissa);
    }

    #[Override]
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool {
        if (!is_int($value) && !is_float($value)) {
            return true;
        }
        if ($this->isMultiple($value)) {
            return true;
        }
        return $run->fail(
            $instanceLocation,
            $keywordLocation . '/multipleOf',
            $this->location,
            sprintf('The value must be a multiple of %s.', (string) $this->divisor),
        );
    }

    /**
     * Whether the value divided by the divisor is a whole number, with both read as the decimals that json_encode()
     * writes for them.
     *
     * @mutation-free
     */
    private function isMultiple(int|float $value): bool
    {
        [$mantissa, $exponent] = self::decimal($value);
        if ($mantissa === 0) {
            return true;
        }
        [, $twos, $fives] = self::factors($mantissa);
        $shift = $exponent - $this->exponent;
        return ($mantissa % $this->rest) === 0 && $this->twos <= ($twos + $shift) && $this->fives <= ($fives + $shift);
    }

    /**
     * Returns the number as $mantissa * 10 ** $exponent.
     *
     * @return array{int, int}
     * @mutation-free
     */
    private static function decimal(int|float $number): array
    {
        if (is_int($number)) {
            return [$number, 0];
        }
        /** @var array{string, string, int|null} $parts */
        $parts = sscanf(var_export($number, return: true), '%[-0-9].%[0-9]E%d');
        [$whole, $fraction, $power] = $parts;
        return [(int) ($whole . $fraction), ($power ?? 0) - strlen($fraction)];
    }

    /**
     * Splits a number other than 0 into $rest * 2 ** $twos * 5 ** $fives, with a $rest that 2 and 5 don't divide.
     *
     * @return array{int, int, int}
     * @mutation-free
     */
    private static function factors(int $number): array
    {
        $twos = 0;
        while (($number % 2) === 0) {
            $number >>= 1;
            $twos++;
        }
        $fives = 0;
        while (($number % 5) === 0) {
            /** @var int $quotient Dividing a multiple of 5 by 5 gives an int. */
            $quotient = $number / 5;
            $number = $quotient;
            $fives++;
        }
        return [$number, $twos, $fives];
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
