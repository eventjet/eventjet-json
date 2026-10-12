<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function explode;
use function ltrim;
use function preg_match;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/** @internal */
final class PhpDocImportStatement
{
    /**
     * Constant imports use keys prefixed with "const:", which no class alias can collide with.
     *
     * @return array<string, string>
     */
    public static function parse(string $statement): array
    {
        $isFunctionImport = preg_match('/\A\s*function\s/i', $statement) === 1;
        if ($isFunctionImport) {
            return [];
        }
        $keyword = [];
        $constants = preg_match('/\A\s*const\s+/i', $statement, $keyword) === 1;
        $statement = substr($statement, strlen($keyword[0] ?? ''));

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
            $keyword = [];
            $constant = preg_match('/\A\s*const\s+/i', $member, $keyword) === 1;
            $member = substr($member, strlen($keyword[0] ?? ''));
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
            $imports[$constants || $constant ? 'const:' . $alias : strtolower($alias)] = $name;
        }

        return $imports;
    }
}
