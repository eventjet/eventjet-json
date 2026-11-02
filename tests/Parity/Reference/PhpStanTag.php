<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

/**
 * A `@param` or `@var` tag as PHPStan reads it.
 *
 * @internal
 */
final readonly class PhpStanTag
{
    public function __construct(
        public string $name,
        public string $variable,
        public PhpStanType $type,
    ) {}
}
