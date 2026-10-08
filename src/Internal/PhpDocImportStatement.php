<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function explode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function str_contains;
use function strtolower;
use function trim;

/** @internal */
final class PhpDocImportStatement
{
    /** @return array<string, string> */
    public static function parse(string $statement, PhpDocImportKind $kind = PhpDocImportKind::ClassName): array
    {
        $isNonClassImport = preg_match('/\A\s*(?:function|const)\s/i', $statement) === 1;
        $isConstantImport = preg_match('/\A\s*const\s/i', $statement) === 1;
        $selected = $kind === PhpDocImportKind::Constant ? $isConstantImport : !$isNonClassImport;
        if (!$selected && ($isNonClassImport || !str_contains($statement, '{'))) {
            return [];
        }
        $statement = $kind === PhpDocImportKind::Constant
            ? preg_replace('/\A\s*const\s+/i', replacement: '', subject: $statement) ?? ''
            : $statement;

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
            if ($kind === PhpDocImportKind::Constant && !$isConstantImport) {
                $constant = [];
                $matchedConstant = preg_match('/\A\s*const\s+([\s\S]*)\z/i', $member, $constant);
                if ($matchedConstant !== 1) {
                    continue;
                }
                /** @var array{string, string} $constant */
                $member = $constant[1];
            }
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
            $imports[$kind === PhpDocImportKind::Constant ? $alias : strtolower($alias)] = $name;
        }

        return $imports;
    }
}
