<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function explode;
use function implode;
use function preg_match;
use function preg_replace;

/** @internal */
final class PhpDocConstantImportStatement
{
    /** @return array<string, string> */
    public static function parse(string $statement): array
    {
        $imports = [];
        foreach (PhpDocImportStatement::members(self::normalize($statement)) as [$alias, $name]) {
            $imports[$alias] = $name;
        }
        return $imports;
    }

    private static function normalize(string $statement): string
    {
        $count = 0;
        $constants = (string) preg_replace('/\A\s*const\s+/i', replacement: '', subject: $statement, count: $count);
        if ($count === 1) {
            return $constants;
        }
        $group = explode('{', $statement);
        $members = [];
        foreach (explode(',', $group[1] ?? '') as $member) {
            $constant = [];
            $matched = preg_match('/\A\s*const\s+([\s\S]*)\z/i', $member, $constant) === 1;
            if ($matched) {
                /** @var array{string, string} $constant */
                $members[] = $constant[1];
            }
        }
        return $group[0] . '{' . implode(',', $members);
    }
}
