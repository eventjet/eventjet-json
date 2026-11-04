<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function preg_match;
use function strpos;
use function substr;

/**
 * Translates the Unicode properties of ECMA-262, such as `Letter` or `Script=Greek`, into their PCRE names.
 *
 * @internal
 */
final class UnicodeProperties
{
    private const array GENERAL_CATEGORIES = [
        'L' => 'L',
        'Letter' => 'L',
        'LC' => 'L&',
        'Cased_Letter' => 'L&',
        'Lu' => 'Lu',
        'Uppercase_Letter' => 'Lu',
        'Ll' => 'Ll',
        'Lowercase_Letter' => 'Ll',
        'Lt' => 'Lt',
        'Titlecase_Letter' => 'Lt',
        'Lm' => 'Lm',
        'Modifier_Letter' => 'Lm',
        'Lo' => 'Lo',
        'Other_Letter' => 'Lo',
        'M' => 'M',
        'Mark' => 'M',
        'Combining_Mark' => 'M',
        'Mn' => 'Mn',
        'Nonspacing_Mark' => 'Mn',
        'Mc' => 'Mc',
        'Spacing_Mark' => 'Mc',
        'Me' => 'Me',
        'Enclosing_Mark' => 'Me',
        'N' => 'N',
        'Number' => 'N',
        'Nd' => 'Nd',
        'Decimal_Number' => 'Nd',
        'digit' => 'Nd',
        'Nl' => 'Nl',
        'Letter_Number' => 'Nl',
        'No' => 'No',
        'Other_Number' => 'No',
        'P' => 'P',
        'Punctuation' => 'P',
        'punct' => 'P',
        'Pc' => 'Pc',
        'Connector_Punctuation' => 'Pc',
        'Pd' => 'Pd',
        'Dash_Punctuation' => 'Pd',
        'Ps' => 'Ps',
        'Open_Punctuation' => 'Ps',
        'Pe' => 'Pe',
        'Close_Punctuation' => 'Pe',
        'Pi' => 'Pi',
        'Initial_Punctuation' => 'Pi',
        'Pf' => 'Pf',
        'Final_Punctuation' => 'Pf',
        'Po' => 'Po',
        'Other_Punctuation' => 'Po',
        'S' => 'S',
        'Symbol' => 'S',
        'Sm' => 'Sm',
        'Math_Symbol' => 'Sm',
        'Sc' => 'Sc',
        'Currency_Symbol' => 'Sc',
        'Sk' => 'Sk',
        'Modifier_Symbol' => 'Sk',
        'So' => 'So',
        'Other_Symbol' => 'So',
        'Z' => 'Z',
        'Separator' => 'Z',
        'Zs' => 'Zs',
        'Space_Separator' => 'Zs',
        'Zl' => 'Zl',
        'Line_Separator' => 'Zl',
        'Zp' => 'Zp',
        'Paragraph_Separator' => 'Zp',
        'C' => 'C',
        'Other' => 'C',
        'Cc' => 'Cc',
        'Control' => 'Cc',
        'cntrl' => 'Cc',
        'Cf' => 'Cf',
        'Format' => 'Cf',
        'Cs' => 'Cs',
        'Surrogate' => 'Cs',
        'Co' => 'Co',
        'Private_Use' => 'Co',
        'Cn' => 'Cn',
        'Unassigned' => 'Cn',
    ];

    /** Returns the PCRE name of a property, or null if ECMA-262 doesn't know it. */
    public static function resolve(string $name): string|null
    {
        $valid = preg_match('/^[A-Za-z0-9_]+(=[A-Za-z0-9_]+)?$/', $name) === 1;
        if (!$valid) {
            return null;
        }
        $equals = strpos($name, needle: '=');
        if ($equals === false) {
            return self::GENERAL_CATEGORIES[$name] ?? $name;
        }
        $value = substr($name, $equals + 1);
        return match (substr($name, offset: 0, length: $equals)) {
            'General_Category', 'gc' => self::GENERAL_CATEGORIES[$value] ?? null,
            'Script', 'sc' => 'sc:' . $value,
            'Script_Extensions', 'scx' => 'scx:' . $value,
            default => null,
        };
    }
}
