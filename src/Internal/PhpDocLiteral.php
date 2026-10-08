<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;

use function constant;
use function defined;
use function is_float;
use function is_int;
use function preg_match;
use function str_contains;

/** @internal */
final class PhpDocLiteral
{
    public static function value(string $name): string|int|float|bool|BackedEnum|null
    {
        $scoped = preg_match('/\A(?:self|static|parent)::/i', $name) === 1;
        if ($scoped) {
            return null;
        }
        if (str_contains($name, '::') && defined($name)) {
            /**
             * @var mixed $value
             * @mago-expect analysis:unhandled-thrown-type The complete constant name was checked with defined().
             */
            $value = constant($name);
            return $value instanceof BackedEnum ? $value : null;
        }
        if ($name === 'true' || $name === 'false') {
            return $name === 'true';
        }
        $literalSyntax = preg_match('/\A[\x27"0-9.+-]/', $name);
        if ($literalSyntax !== 1) {
            return null;
        }
        return $name[0] === "'" || $name[0] === '"'
            ? PhpDocLiteralString::parse($name)
            : PhpDocLiteralNumber::parse($name);
    }

    public static function matches(string $name, mixed $value): bool
    {
        $literal = self::value($name);
        return (
            $literal !== null
            && (
                $literal === $value
                || is_float($literal)
                && is_int($value)
                && $literal === (float) $value
                || $literal instanceof BackedEnum
                && $literal->value === $value
            )
        );
    }
}
