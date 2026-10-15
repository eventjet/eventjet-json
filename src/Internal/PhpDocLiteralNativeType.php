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
    public static function name(PhpDocLiteral $literal, array $expected): string
    {
        $literal = $literal->value;
        if (is_bool($literal) && !in_array('bool', $expected, strict: true)) {
            return $literal ? 'true' : 'false';
        }
        return get_debug_type($literal);
    }
}
