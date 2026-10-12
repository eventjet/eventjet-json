<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;

use function strspn;

/** @internal */
final class PhpDocLiteralTypeTokens extends PhpDocTypeTokens
{
    /** @return non-empty-string */
    #[Override]
    protected function pattern(): string
    {
        return strspn($this->source, characters: "'\"+-.0123456789", offset: $this->offset, length: 1) === 1
            ? '/\G(?:\x27(?:[^\x27\\\\]|\\\\[\s\S])*\x27|"(?:[^"\\\\]|\\\\[\s\S])*"|[+-]?(?:0[xX][0-9a-fA-F]+(?:_[0-9a-fA-F]+)*|0[bB][01]+(?:_[01]+)*|0[oO][0-7]+(?:_[0-7]+)*|(?:[0-9]+(?:_[0-9]+)*(?:\.[0-9]*(?:_[0-9]+)*)?|\.[0-9]+(?:_[0-9]+)*)(?:[eE][+-]?[0-9]+(?:_[0-9]+)*)?))/'
            : '/\G\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff-]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*(?:::[A-Za-z_*\x80-\xff][A-Za-z0-9_*\x80-\xff]*)?/';
    }
}
