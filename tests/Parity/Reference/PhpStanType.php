<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

use Eventjet\Json\Internal\PhpDocType;

/**
 * A type as PHPStan reads it, translated into the decoder's {@see PhpDocType}, and the number literals in it. The type
 * is null where the decoder does not support the syntax.
 *
 * @internal
 */
final readonly class PhpStanType
{
    /** @param list<PhpStanNumber> $numbers */
    public function __construct(
        public PhpDocType|null $type,
        public array $numbers = [],
    ) {}

    public static function none(): self
    {
        return new self(null);
    }
}
