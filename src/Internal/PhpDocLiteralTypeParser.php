<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;
use UnexpectedValueException;

/** @internal */
final readonly class PhpDocLiteralTypeParser extends PhpDocTypeParser
{
    /** @throws UnexpectedValueException */
    #[Override]
    protected function namedType(int $depth): PhpDocType
    {
        $parenthesized = $this->tokens->consume('(');
        if (!$parenthesized) {
            return parent::namedType($depth);
        }
        $type = $this->type($depth + 1);
        $closed = $this->tokens->consume(')');
        if (!$closed) {
            throw new UnexpectedValueException('Expected a closing PHPDoc parenthesis.');
        }
        return $type;
    }
}
