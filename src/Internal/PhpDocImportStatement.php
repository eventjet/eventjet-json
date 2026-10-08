<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function explode;
use function ltrim;
use function preg_match;
use function strtolower;
use function trim;

/** @internal */
final class PhpDocImportStatement
{
    /** @return array<string, string> */
    public static function parse(string $statement): array
    {
        $isNonClassImport = preg_match('/\A\s*(?:function|const)\s/i', $statement) === 1;
        if ($isNonClassImport) {
            return [];
        }

        $group = explode('{', $statement);
        $prefix = '';
        $members = $statement;

        $groupMembers = $group[1] ?? null;
        if ($groupMembers !== null) {
            $prefix = trim($group[0]);
            $members = trim($groupMembers, characters: " }\t\n\r\0\x0B");
        }

        $imports = [];
        foreach (explode(',', $members) as $member) {
            $matches = [];
            $matched = preg_match(
                '/\A\s*(\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff\\\\]*)(?:\s+as\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*))?\s*\z/i',
                $member,
                $matches,
            );
            if ($matched !== 1) {
                continue;
            }

            /** @var array{0: non-falsy-string, 1: non-falsy-string, 2?: non-falsy-string} $matches */
            $name = ltrim($prefix . $matches[1], characters: '\\');
            $parts = explode('\\', $name);
            $alias = $matches[2] ?? array_pop($parts);
            $imports[strtolower($alias)] = $name;
        }

        return $imports;
    }
}
