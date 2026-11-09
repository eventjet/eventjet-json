<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Keyword;

use Eventjet\Json\Internal\Schema\Annotations;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Run;
use Override;

use function abs;
use function fdiv;
use function floor;
use function fmod;
use function is_finite;
use function is_float;
use function is_int;
use function round;
use function sprintf;

/** @internal */
final readonly class MultipleOfKeyword implements Keyword
{
    public function __construct(
        private int|float $divisor,
        private string $location,
    ) {}

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

    /** @mutation-free */
    private function isMultiple(int|float $value): bool
    {
        if (is_int($value) && is_int($this->divisor)) {
            return ($value % $this->divisor) === 0;
        }
        if (floor($value) === (float) $value && floor($this->divisor) === (float) $this->divisor) {
            return fmod((float) $value, (float) $this->divisor) === 0.0;
        }
        $quotient = fdiv($value, $this->divisor);
        if (!is_finite($quotient)) {
            return fmod((float) $value, (float) $this->divisor) === 0.0;
        }
        return abs($quotient - round($quotient)) < 1e-9;
    }

    #[Override]
    public function inPlace(): array
    {
        return [];
    }
}
