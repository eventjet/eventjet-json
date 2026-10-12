<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function explode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function trim;

/**
 * Constant imports use keys prefixed with "const:", which no class alias can collide with.
 *
 * @internal
 */
final class PhpDocConstantImportStatement
{
    /** @return array<string, string> */
    public static function parse(string $statement): array
    {
        $constants = preg_replace('/\A\s*const\s+/i', replacement: '', subject: $statement) ?? '';
        if ($constants === $statement) {
            return [];
        }
        $group = explode('{', $constants);
        $groupMembers = $group[1] ?? null;
        $prefix = $groupMembers === null ? '' : trim($group[0]);
        $imports = [];
        foreach (explode(',', $groupMembers ?? $group[0]) as $member) {
            $imports = [...$imports, ...self::import($prefix, $member)];
        }
        return $imports;
    }

    /** @return array<string, string> */
    public static function groupMember(string $prefix, string $member): array
    {
        $constant = [];
        preg_match('/\A\s*const\s+([\s\S]*)\z/i', $member, $constant);
        return self::import($prefix, $constant[1] ?? '');
    }

    /** @return array<string, string> */
    private static function import(string $prefix, string $member): array
    {
        $matches = [];
        $matched = preg_match(
            '/\A\s*(\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff\\\\]*)(?:\s+as\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*))?\s*\z/i',
            trim($member, characters: " }\t\n\r\0\x0B"),
            $matches,
        );
        if ($matched !== 1) {
            return [];
        }

        /** @var array{0: non-falsy-string, 1: non-falsy-string, 2?: non-falsy-string} $matches */
        $name = ltrim($prefix . $matches[1], characters: '\\');
        $parts = explode('\\', $name);
        return ['const:' . ($matches[2] ?? array_pop($parts)) => $name];
    }
}
