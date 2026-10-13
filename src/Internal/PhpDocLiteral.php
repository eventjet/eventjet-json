<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;

use function constant;
use function defined;
use function enum_exists;
use function strstr;
use function strtr;
use function substr;

/** @internal */
final class PhpDocLiteral
{
    public static function value(string $name): string|int|float|bool|BackedEnum|null
    {
        $owner = strstr($name, needle: '::', before_needle: true);
        $enumCase = $owner !== false && enum_exists($owner);
        if ($enumCase && defined($name)) {
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
        $quote = $name[0] ?? '';
        if ($quote !== "'" && $quote !== '"') {
            return PhpDocLiteralNumber::parse($name);
        }
        return strtr(substr($name, offset: 1, length: -1), ['\\\\' => '\\', "\\'" => "'", '\\"' => '"']);
    }

    /**
     * Returns the name of a parsed string literal. Only backslashes and the quote are escaped, so that {@see value()}
     * can restore the string.
     *
     * @param "'"|'"' $quote
     */
    public static function quote(string $value, string $quote): string
    {
        return $quote . strtr($value, ['\\' => '\\\\', $quote => '\\' . $quote]) . $quote;
    }
}
