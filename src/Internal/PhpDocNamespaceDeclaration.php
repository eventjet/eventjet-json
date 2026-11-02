<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;
use function ord;

use const T_NAME_QUALIFIED;
use const T_STRING;

/** @internal */
final class PhpDocNamespaceDeclaration
{
    public static function read(PhpDocTokenStream $tokens): string
    {
        $name = '';
        for ($token = $tokens->current(); $token !== null; $tokens->advance(), $token = $tokens->current()) {
            if (in_array($token->id, [ord('{'), ord(';')], strict: true)) {
                break;
            }
            if (in_array($token->id, [T_NAME_QUALIFIED, T_STRING], strict: true)) {
                $name = $token->text;
            }
        }

        return $name;
    }
}
