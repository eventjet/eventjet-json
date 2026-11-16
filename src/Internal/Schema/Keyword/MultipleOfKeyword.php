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
    /** The divisor as $mantissa * 10 ** $exponent. */
    private int $mantissa;
    private int $exponent;

    public function __construct(
        private int|float $divisor,
        private string $location,
    ) {
        [$this->mantissa, $this->exponent] = self::decimal($divisor);
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
        $divisor = $this->mantissa;
        $shift = $exponent - $this->exponent;
        for ($i = $shift; $i < 0; $i++) {
            if (($mantissa % 10) !== 0) {
                return false;
            }
            /** @var int $quotient Dividing a multiple of 10 by 10 gives an int. */
            $quotient = $mantissa / 10;
            $mantissa = $quotient;
        }
        for ($i = $shift; $i > 0; $i--) {
            if (($divisor % 2) === 0) {
                /** @var int $quotient Dividing a multiple of 2 by 2 gives an int. */
                $quotient = $divisor / 2;
                $divisor = $quotient;
            }
            if (($divisor % 5) === 0) {
                /** @var int $quotient Dividing a multiple of 5 by 5 gives an int. */
                $quotient = $divisor / 5;
                $divisor = $quotient;
            }
        }
        return ($mantissa % $divisor) === 0;
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

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
