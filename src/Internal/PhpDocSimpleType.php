<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function count;
use function preg_match_all;
use function substr_count;

/**
 * Parses a single-line type for {@see PhpDocSimpleTags}: names, generics, unions, integers, quoted strings without
 * escapes, and class constants such as `Foo::BAR`. Returns null for any other type, which then needs the full parser,
 * for the names `covariant` and `contravariant`, and for types with 64 or more `<`.
 *
 * @internal
 */
final class PhpDocSimpleType
{
    private const string NAME = '\\\\?+[A-Z_a-z][-0-9A-Z_a-z]*+(?:\\\\[A-Z_a-z][-0-9A-Z_a-z]*+)*+';

    /** The constant expression parser reads these names as keywords, so they cannot start a class constant. */
    private const string KEYWORD = '(?:[Tt][Rr][Uu][Ee]|[Ff][Aa][Ll][Ss][Ee]|[Nn][Uu][Ll][Ll]|[Aa][Rr][Rr][Aa][Yy])::';

    /** phpdoc-parser reads these names as the variance of a generic argument. */
    private const string VARIANCE = '(?:co|contra)variant(?![-0-9A-Z\\\\_a-z])';

    private const string TOKEN =
        '~\G[ \t]*+\K(?:'
            . '(?!'
            . self::KEYWORD
            . ')'
            . self::NAME
            . '::[A-Z_a-z][0-9A-Z_a-z]*+(*MARK:value)'
            . '|(?!'
            . self::VARIANCE
            . ')'
            . self::NAME
            . '(*MARK:name)'
            . '|(?:\'[^"\'\\\\]*+\'|"[^"\'\\\\]*+"|-?+[0-9]++)(*MARK:value)'
            . '|[<>,|](*MARK:punctuation)'
            . '|[^ \t](*MARK:other))~';

    private int $index = 0;

    /**
     * @param list<string> $values
     * @param list<string> $kinds
     */
    private function __construct(
        private readonly array $values,
        private readonly array $kinds,
    ) {}

    public static function parse(string $source): PhpDocType|null
    {
        // A type nested 64 levels deep has at least 64 `<`, and only the full parser applies the depth limit.
        if (substr_count($source, needle: '<') >= 64) {
            return null;
        }
        $matches = [];
        preg_match_all(self::TOKEN, $source, $matches);
        // Every character outside whitespace starts a token, so the parser fails on anything that the grammar does not cover.
        /** @var array{0: list<string>, MARK?: list<string>} $matches */
        $parser = new self($matches[0], $matches['MARK'] ?? []);
        $type = $parser->union();
        return $parser->index === count($parser->kinds) ? $type : null;
    }

    private function union(): PhpDocType|null
    {
        $members = [];
        $more = true;
        while ($more) {
            $member = $this->atomic();
            if ($member === null) {
                return null;
            }
            $members[] = $member;
            $more = $this->accept('|');
        }
        return count($members) === 1 ? $members[0] : new PhpDocType('|', $members);
    }

    private function atomic(): PhpDocType|null
    {
        $kind = $this->kinds[$this->index] ?? '';
        $value = $this->values[$this->index] ?? '';
        $this->index++;
        if ($kind === 'value') {
            return new PhpDocType($value);
        }
        if ($kind !== 'name') {
            return null;
        }
        $generic = $this->accept('<');
        return $generic ? $this->generic($value) : new PhpDocType($value);
    }

    private function generic(string $name): PhpDocType|null
    {
        $arguments = [];
        $more = true;
        while ($more) {
            $argument = $this->union();
            if ($argument === null) {
                return null;
            }
            $arguments[] = $argument;
            $more = $this->accept(',');
        }
        $closed = $this->accept('>');
        return $closed ? new PhpDocType($name, $arguments) : null;
    }

    private function accept(string $punctuation): bool
    {
        $accepted = ($this->values[$this->index] ?? '') === $punctuation;
        $this->index += (int) $accepted;
        return $accepted;
    }
}
