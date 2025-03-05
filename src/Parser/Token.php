<?php

declare(strict_types=1);

namespace Eventjet\Json\Parser;

use function is_string;
use function str_replace;

enum Token: string
{
    case OpenCurly = '{';
    case CloseCurly = '}';
    case Colon = ':';
    case Comma = ',';
    case OpenBracket = '[';
    case CloseBracket = ']';

    public static function print(self|string|bool|int|float|null $token): string
    {
        if ($token instanceof self) {
            return $token->value;
        }
        if (is_string($token)) {
            return '"' . str_replace(['\\', "\n"], ['\\\\', '\n'], $token) . '"';
        }
        return match ($token) {
            null => 'null',
            true => 'true',
            false => 'false',
            default => (string)$token,
        };
    }
}
