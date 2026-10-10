<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function get_debug_type;
use function in_array;
use function is_bool;

/** @internal */
final class PhpDocLiteralNativeType
{
    /** @param list<string> $expected */
    public static function name(string $name, array $expected): string
    {
        $literal = PhpDocType::literalSyntax($name) ? PhpDocLiteral::value($name) : null;
        if ($literal === null) {
            return $name;
        }
        if (is_bool($literal) && !in_array('bool', $expected, strict: true)) {
            return $literal ? 'true' : 'false';
        }
        return get_debug_type($literal);
    }
}
