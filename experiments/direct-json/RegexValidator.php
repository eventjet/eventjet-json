<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark\Prototype;

/** PROTOTYPE: a bounded fast path; native validation remains the authority. */
final class RegexValidator
{
    public static function validate(string $json): bool
    {
        // Even brackets inside strings count here. A false positive just takes
        // the native path. Below 512 total openers, nesting cannot reach 512.
        if ((substr_count($json, '{') + substr_count($json, '[')) >= 512) {
            return json_validate($json);
        }
        $pattern = <<<'REGEX'
            ~(?(DEFINE)
             (?<w>[\x20\x09\x0a\x0d]*+)
             (?<s>"(?:[^"\\\x00-\x1f]++|\\(?:["\\/bfnrt]|u(?:[0-9a-cA-Ce-fE-F][0-9a-fA-F]{3}|[dD][0-7][0-9a-fA-F]{2}|[dD][89aAbB][0-9a-fA-F]{2}\\u[dD][c-fC-F][0-9a-fA-F]{2})))*+")
             (?<n>-?(?:0|[1-9][0-9]*+)(?:\.[0-9]++)?(?:[eE][+-]?[0-9]++)?)
             (?<v>(?&s)|(?&n)|true|false|null|(?&a)|(?&o))
             (?<a>\[(?&w)(?:(?&v)(?&w)(?:,(?&w)(?&v)(?&w))*+)?\])
             (?<o>\{(?&w)(?:(?&s)(?&w):(?&w)(?&v)(?&w)(?:,(?&w)(?&s)(?&w):(?&w)(?&v)(?&w))*+)?\})
             )\A(?&w)(?&v)(?&w)\z\K~ux
            REGEX;
        // The empty match at \K prevents copying a multi-megabyte root string.
        if (preg_match($pattern, $json) === 1) {
            // Preserve json_decode's successful json_last_error() state as well.
            json_validate('null');
            return true;
        }
        return json_validate($json);
    }
}
