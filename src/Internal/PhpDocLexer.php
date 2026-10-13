<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_map;
use function implode;
use function preg_match;
use function preg_match_all;

/**
 * Splits a doc comment into tokens exactly like phpstan/phpdoc-parser 2.x, so that tags and types are read the way
 * PHPStan reads them.
 *
 * @internal
 */
final class PhpDocLexer
{
    public const int REFERENCE = 0;
    public const int UNION = 1;
    public const int INTERSECTION = 2;
    public const int NULLABLE = 3;
    public const int OPEN_PARENTHESES = 4;
    public const int CLOSE_PARENTHESES = 5;
    public const int OPEN_ANGLE_BRACKET = 6;
    public const int CLOSE_ANGLE_BRACKET = 7;
    public const int OPEN_SQUARE_BRACKET = 8;
    public const int CLOSE_SQUARE_BRACKET = 9;
    public const int COMMA = 10;
    public const int VARIADIC = 11;
    public const int DOUBLE_COLON = 12;
    public const int DOUBLE_ARROW = 13;
    public const int EQUAL = 14;
    public const int OPEN_PHPDOC = 15;
    public const int CLOSE_PHPDOC = 16;
    public const int PHPDOC_TAG = 17;
    public const int DOCTRINE_TAG = 18;
    public const int FLOAT = 19;
    public const int INTEGER = 20;
    public const int SINGLE_QUOTED_STRING = 21;
    public const int DOUBLE_QUOTED_STRING = 22;
    public const int DOCTRINE_ANNOTATION_STRING = 23;
    public const int IDENTIFIER = 24;
    public const int THIS_VARIABLE = 25;
    public const int VARIABLE = 26;
    public const int HORIZONTAL_WS = 27;
    public const int PHPDOC_EOL = 28;
    public const int OTHER = 29;
    public const int END = 30;
    public const int COLON = 31;
    public const int WILDCARD = 32;
    public const int OPEN_CURLY_BRACKET = 33;
    public const int CLOSE_CURLY_BRACKET = 34;
    public const int NEGATED = 35;
    public const int ARROW = 36;
    public const int COMMENT = 37;

    private const string NAME = '(?:[\\\\]?+[a-z_\\x80-\\xFF][0-9a-z_\\x80-\\xFF-]*+)++';

    /** The order decides which token wins where several patterns match. */
    private const array PATTERNS = [
        self::HORIZONTAL_WS => '[\\x09\\x20]++',
        self::IDENTIFIER => self::NAME,
        self::THIS_VARIABLE => '\\$this(?![0-9a-z_\\x80-\\xFF])',
        self::VARIABLE => '\\$[a-z_\\x80-\\xFF][0-9a-z_\\x80-\\xFF]*+',
        self::REFERENCE => '&(?=\\s*+(?:[,=)]|\\.\\.\\.|(?:\\$(?!this(?![0-9a-z_\\x80-\\xFF])))))',
        self::UNION => '\\|',
        self::INTERSECTION => '&',
        self::NULLABLE => '\\?',
        self::NEGATED => '!',
        self::OPEN_PARENTHESES => '\\(',
        self::CLOSE_PARENTHESES => '\\)',
        self::OPEN_ANGLE_BRACKET => '<',
        self::CLOSE_ANGLE_BRACKET => '>',
        self::OPEN_SQUARE_BRACKET => '\\[',
        self::CLOSE_SQUARE_BRACKET => '\\]',
        self::OPEN_CURLY_BRACKET => '\\{',
        self::CLOSE_CURLY_BRACKET => '\\}',
        self::COMMA => ',',
        self::COMMENT => '\/\/[^\\r\\n]*(?=\n|\r|\*/)',
        self::VARIADIC => '\\.\\.\\.',
        self::DOUBLE_COLON => '::',
        self::DOUBLE_ARROW => '=>',
        self::ARROW => '->',
        self::EQUAL => '=',
        self::COLON => ':',
        self::OPEN_PHPDOC => '/\\*\\*(?=\\s)\\x20?+',
        self::CLOSE_PHPDOC => '\\*/',
        self::PHPDOC_TAG => '@(?:[a-z][a-z0-9-\\\\]+:)?[a-z][a-z0-9-\\\\]*+',
        self::DOCTRINE_TAG => '@[a-z_\\\\][a-z0-9_\:\\\\]*[a-z_][a-z0-9_]*',
        self::PHPDOC_EOL => '\\r?+\\n[\\x09\\x20]*+(?:\\*(?!/)\\x20?+)?',
        self::FLOAT => '[+\-]?(?:(?:[0-9]++(?:_[0-9]++)*\\.[0-9]*+(?:_[0-9]++)*(?:e[+\-]?[0-9]++(?:_[0-9]++)*)?)|(?:[0-9]*+(?:_[0-9]++)*\\.[0-9]++(?:_[0-9]++)*(?:e[+\-]?[0-9]++(?:_[0-9]++)*)?)|(?:[0-9]++(?:_[0-9]++)*e[+\-]?[0-9]++(?:_[0-9]++)*))',
        self::INTEGER => '[+\-]?(?:(?:0b[0-1]++(?:_[0-1]++)*)|(?:0o[0-7]++(?:_[0-7]++)*)|(?:0x[0-9a-f]++(?:_[0-9a-f]++)*)|(?:[0-9]++(?:_[0-9]++)*))',
        self::SINGLE_QUOTED_STRING => '\'(?:\\\\[^\\r\\n]|[^\'\\r\\n\\\\])*+\'',
        self::DOUBLE_QUOTED_STRING => '"(?:\\\\[^\\r\\n]|[^"\\r\\n\\\\])*+"',
        self::DOCTRINE_ANNOTATION_STRING => '"(?:""|[^"])*+"',
        self::WILDCARD => '\\*',
        self::OTHER => '(?:(?!\\*/)[^\\s])++',
    ];

    /**
     * Returns the token values and types, ending with an empty end token. Tokenization stops at the first character
     * that no pattern matches.
     *
     * @return array{non-empty-list<string>, non-empty-list<int>}
     */
    public static function tokenize(string $source): array
    {
        $matches = [];
        preg_match_all(self::pattern(), $source, $matches);
        /** @var array{0: list<string>, MARK?: list<string>} $matches */
        $values = $matches[0];
        $types = array_map(intval(...), $matches['MARK'] ?? []);
        $values[] = '';
        $types[] = self::END;
        return [$values, $types];
    }

    /** @mutation-free */
    public static function isIdentifier(string $value): bool
    {
        return preg_match('~\\A' . self::NAME . '\\z~i', $value) === 1;
    }

    /**
     * Each pattern has no alternatives at its top level, so it needs no group.
     *
     * @return non-empty-string
     */
    private static function pattern(): string
    {
        $alternatives = [];
        foreach (self::PATTERNS as $type => $pattern) {
            $alternatives[] = $pattern . '(*MARK:' . $type . ')';
        }
        return '~' . implode('|', $alternatives) . '~Asi';
    }
}
