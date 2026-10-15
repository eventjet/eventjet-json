<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

/**
 * A number literal in a type, with the value that PHPStan gives it.
 *
 * @internal
 */
final readonly class PhpStanNumber
{
    /** @param bool $bound Whether it is a bound of an integer range such as `int<0, max>`. */
    public function __construct(
        public string $literal,
        public int|float $value,
        public bool $bound,
    ) {}
}
