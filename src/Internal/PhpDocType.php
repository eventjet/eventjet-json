<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class PhpDocType
{
    /**
     * @param list<self> $arguments
     * @param list<PhpDocTupleEntry> $entries
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
        public array $entries = [],
    ) {}
}
