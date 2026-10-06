<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class TupleType
{
    /** @param list<'bool'|'float'|'int'|'string'|class-string> $types */
    public function __construct(
        public array $types,
        public int $required,
    ) {}
}
