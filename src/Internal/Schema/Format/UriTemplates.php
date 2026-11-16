<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function array_slice;
use function explode;
use function preg_match;
use function str_ends_with;
use function strpos;
use function strspn;
use function substr;

/**
 * Checks for the format of URI templates. Like {@see UriSyntax}, it splits values with string functions and checks the
 * parts with {@see Units}.
 *
 * @internal
 */
final class UriTemplates
{
    private const string PCT = '%[0-9A-Fa-f]{2}';
    /** A run of varchars, with a dot after it only if another varchar follows. */
    private const string NAME = '(?:[A-Za-z0-9_]++|' . self::PCT . ')(?:\.(?=[A-Za-z0-9_%]))?';

    public static function uriTemplate(string $value): bool
    {
        $parts = explode('{', $value);
        $valid = self::literals($parts[0]);
        foreach (array_slice($parts, offset: 1) as $part) {
            $close = strpos($part, needle: '}');
            $valid =
                $valid
                && $close !== false
                && self::expression(substr($part, offset: 0, length: $close))
                && self::literals(substr($part, $close + 1));
        }
        return $valid;
    }

    private static function literals(string $text): bool
    {
        return Units::cover($text, '[^\x00-\x20"%<>\\\\^`{|}\x7F]++|' . self::PCT);
    }

    /** Checks the inside of an expression: an optional operator and variables separated by commas. */
    private static function expression(string $expression): bool
    {
        $operator = strspn($expression, characters: '+#./;?&=,!@|', offset: 0, length: 1);
        $valid = true;
        foreach (explode(',', substr($expression, $operator)) as $variable) {
            $valid = $valid && self::variable($variable);
        }
        return $valid;
    }

    /** Checks a variable name with an optional explode modifier or prefix length. */
    private static function variable(string $variable): bool
    {
        if (str_ends_with($variable, '*')) {
            return self::name(substr($variable, offset: 0, length: -1));
        }
        $colon = strpos($variable, needle: ':');
        if ($colon === false) {
            return self::name($variable);
        }
        return (
            self::name(substr($variable, offset: 0, length: $colon))
            && preg_match('/^[1-9]\d{0,3}$/D', substr($variable, $colon + 1)) === 1
        );
    }

    /** A name is varchars with single dots between them. */
    private static function name(string $name): bool
    {
        return $name !== '' && Units::cover($name, self::NAME);
    }
}
