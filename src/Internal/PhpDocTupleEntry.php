<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
final readonly class PhpDocTupleEntry
{
    public function __construct(
        public PhpDocType $type,
        public string|null $key = null,
        public bool $optional = false,
    ) {}
}
