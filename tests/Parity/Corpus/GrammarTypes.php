<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function implode;

/**
 * Builds random types from the whole PHPDoc type grammar, including invalid variations and HTML that phpdoc-parser
 * skips.
 *
 * @internal
 */
final readonly class GrammarTypes
{
    private const array IDENTIFIERS = [
        'int',
        'string',
        'float',
        'bool',
        'null',
        'true',
        'false',
        'mixed',
        'list',
        'array',
        'non-empty-list',
        'non-empty-array',
        'non-empty-map',
        'ArrayObject',
        '\ArrayObject',
        'Foo',
        '\Foo\Bar',
        'Foo\Bar',
        'self',
        'static',
        'parent',
        'callable',
        'object',
        'iterable',
        'positive-int',
        'non-empty-string',
        'is',
        'not',
        'of',
        'as',
        'covariant',
        'contravariant',
        'super',
        'from',
        'TRUE',
        'Null',
        'Foo-bar',
        '\Foo\Bar-baz',
        'Fü',
        'namespace\Foo',
        'int-mask',
        'key-of',
        'value-of',
        'class-string',
        'resource',
        'never',
        'void',
        'max',
        'min',
        'array-key',
    ];

    private const array GENERICS = [
        'list',
        'array',
        'non-empty-list',
        'non-empty-array',
        'ArrayObject',
        'int',
        'Foo',
        'iterable',
        'class-string',
        'key-of',
    ];

    private GrammarLiterals $literals;
    private GrammarCallables $callables;
    private GrammarShapes $shapes;

    public function __construct(
        private Dice $dice,
    ) {
        $this->literals = new GrammarLiterals($dice);
        $this->callables = new GrammarCallables($dice, $this);
        $this->shapes = new GrammarShapes($dice, $this);
    }

    /** Returns nothing, a space, or a line break as it appears in a doc comment. */
    public function space(): string
    {
        $lineBreak = $this->dice->pick(["\n * ", "\n *", "\r\n * ", "\n", "\n\t * ", "\n * \n * ", "\n *   "]);
        return $this->dice->either(6, $lineBreak, $this->inlineSpace());
    }

    /** Returns nothing or a space. */
    public function inlineSpace(): string
    {
        return $this->dice->either(50, $this->dice->pick([' ', ' ', '  ', "\t", " \t"]));
    }

    public function type(int $depth = 0): string
    {
        $roll = $depth > 4 ? 1 : $this->dice->int(1, 100);
        return match (true) {
            $roll <= 22 => $this->dice->pick(self::IDENTIFIERS),
            $roll <= 36 => $this->generic($depth),
            $roll <= 46 => $this->compound('|', $depth),
            $roll <= 49 => $this->compound('&', $depth),
            $roll <= 53 => '(' . $this->space() . $this->type($depth + 1) . $this->space() . ')',
            $roll <= 55 => '?' . $this->type($depth + 1),
            $roll <= 58 => $this->type($depth + 1)
                . $this->dice->pick(['[]', ' []', '[int]', '[' . $this->type(5) . ']']),
            $roll <= 70 => $this->shapes->shape($depth),
            $roll <= 73 => $this->callables->callable($depth),
            $roll <= 75 => $this->callables->conditional($depth),
            default => $this->literals->atom($roll),
        };
    }

    /** Nests types around the 64-level limit. */
    public function deep(): string
    {
        $type = $this->dice->pick(['int', "'a'", 'Foo', '42']);
        for ($level = $this->dice->int(60, 110); $level > 0; $level--) {
            $wrapper = $this->dice->int(1, 6);
            $type = match ($wrapper) {
                1, 2 => 'list<' . $this->inlineSpace() . $type . '>',
                3 => 'array{0?: ' . $type . '}',
                4 => '(' . $type . ')',
                5 => $type . '|null',
                default => 'non-empty-array<string, ' . $type . '>',
            };
        }
        return $type;
    }

    private function generic(int $depth): string
    {
        $arguments = [];
        for ($argument = $this->dice->int(0, 3); $argument > 0; $argument--) {
            $variance = $this->dice->either(5, $this->dice->pick(['covariant ', 'contravariant ', '']));
            $wildcard = $this->dice->chance(4);
            $arguments[] = $wildcard ? '*' : $variance . $this->type($depth + 1);
        }
        $separator = $this->dice->pick([', ', ',', ' , ', ",\n * "]);
        $trailing = $this->dice->either(8, ',');
        $open = $this->dice->either(5, ' <', '<');
        return (
            $this->dice->pick(self::GENERICS)
            . $open
            . $this->space()
            . implode($separator, $arguments)
            . $trailing
            . $this->space()
            . '>'
        );
    }

    private function compound(string $operator, int $depth): string
    {
        $result = $this->type($depth + 1);
        for ($member = $this->dice->int(1, 2); $member > 0; $member--) {
            $result .= $this->space() . $operator . $this->space() . $this->type($depth + 1);
        }
        return $result;
    }
}
