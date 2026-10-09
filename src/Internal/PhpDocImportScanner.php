<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function in_array;
use function ord;

use const T_CLASS;
use const T_COMMENT;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

/** @internal */
final class PhpDocImportScanner
{
    /** @return array<string, string> */
    public static function scan(
        string $source,
        int $startLine,
        string $className,
        string $targetNamespace,
        PhpDocImportKind $kind = PhpDocImportKind::ClassName,
    ): array {
        $source = PhpDocImports::sourceBeforeLine($source, $startLine);

        $imports = [];
        $depth = 0;
        $statement = null;
        $namespace = '';
        $previousToken = null;
        $tokens = new PhpDocTokenStream($source);

        for ($token = $tokens->current(); $token !== null; $tokens->advance(), $token = $tokens->current()) {
            if (in_array($token->id, [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], strict: true)) {
                if ($statement !== null) {
                    $statement .= ' ';
                }
                continue;
            }

            $previousDeclaration = in_array($previousToken?->id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], strict: true);
            if (
                $previousDeclaration
                && $token->text === $className
                && $token->line === $startLine
                && $namespace === $targetNamespace
            ) {
                break;
            }
            $previousToken = $token;

            if ($statement !== null) {
                if ($token->text === '(') {
                    $statement = null;
                    continue;
                }
                if ($token->text !== ';') {
                    $statement .= $token->text;
                    continue;
                }
                $imports = [...$imports, ...PhpDocImportStatement::parse($statement, $kind)];
                $statement = null;
                continue;
            }

            if ($token->id === T_NAMESPACE) {
                $imports = [];
                $namespace = PhpDocNamespaceDeclaration::read($tokens);
                $depth = 0;
                continue;
            }

            $depth = match ($token->id) {
                ord('{'), T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES => $depth + 1,
                ord('}') => $depth - 1,
                default => $depth,
            };
            if ($token->id === T_USE && $depth === 0) {
                $statement = '';
            }
        }

        return $imports;
    }
}
