<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use RuntimeException;

use function implode;

/**
 * Doc comments whose tags phpstan/phpdoc-parser reads in a particular way. Each case decodes `[{}]` into an `array`
 * field. Its tags declare lists of different item types, so the error names the item type of the list that was read,
 * or says that no supported collection was.
 *
 * A higher-priority tag whose type is valid syntax takes over even if the decoder does not support the type. A tag
 * whose type is invalid syntax does not count, so the lower-priority tag counts instead.
 *
 * @internal
 */
final class PhpDocTagCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::overridingTypes() as $type => $valid) {
            yield from self::read(
                'a @phpstan-param type ' . $type,
                ['@param list<int> $value', '@phpstan-param ' . $type . ' $value'],
                $valid ? null : 'int',
            );
        }
        foreach (self::html() as $value => $valid) {
            yield from self::read(
                'a @phpstan-param value ' . $value,
                ['@param list<int> $value', '@phpstan-param ' . $value],
                $valid ? null : 'int',
            );
        }
        foreach (self::lines() as $label => [$lines, $expected]) {
            yield from self::read($label, $lines, $expected);
            // Another tag makes the full parser read the doc comment.
            yield from self::read($label . ' after another tag', ['@internal', ...$lines], $expected);
        }
        foreach (self::properties() as $label => [$lines, $expected]) {
            yield from self::read($label, $lines, $expected, 'var');
            yield from self::read($label . ' after another tag', ['@internal', ...$lines], $expected, 'var');
        }
        foreach (self::annotations() as $annotation => $valid) {
            yield from self::read(
                'an annotation ' . $annotation,
                [$annotation, '@param list<int> $value'],
                $valid ? 'int' : null,
            );
        }
    }

    /**
     * Values of a `@phpstan-param` tag that overrides `@param list<int>`, and whether phpdoc-parser reads them as a
     * type followed by the variable. It reads `<` after a name as the start of an HTML element if the matching end tag
     * follows anywhere later, so the type ends before it.
     *
     * @return iterable<string, bool>
     */
    private static function html(): iterable
    {
        yield 'list<b> $value' => true;
        yield 'list<b> $value</b>' => false;
        yield 'list<b> $value </b>' => false;
        yield 'list<b> $value x</b>' => false;
        yield 'list<b> $value < /b>' => false;
        yield 'list<b> $value</B>' => true;
        yield 'list<b> $value</bb>' => true;
        yield 'list<bb> $value</b>' => true;
        yield 'list<b> $value</b' => true;
        yield 'list<b> $value/b>' => true;
        yield 'list<b> $value <i></b>' => false;
        yield 'list<b> $value text</b>more' => false;
        yield 'list<b> $value #</b>' => false;
        yield 'list<b> $value #</b>#' => true;
        yield 'list<b> $value // </b>' => false;
        yield 'list<b>x</b> $value' => false;
        yield 'list<int> $value</int>' => true;
        yield 'list<int> $value Text.' => false;
        yield 'list<b c> $value</b>' => false;
        yield 'list<b>> $value</b>' => false;
        yield 'list <b> $value</b>' => false;
        yield 'list< b > $value</b>' => false;
        yield "list<b> \$value\n * </b>" => false;
        yield "list<b> \$value\n * @return </b>" => false;
        yield 'list<\\b> $value</\\b>' => false;
    }

    /**
     * Lines of a constructor's doc comment, and the item type of the list that phpdoc-parser reads for `$value`.
     *
     * @return iterable<string, array{list<string>, string|null}>
     */
    private static function lines(): iterable
    {
        yield 'a later @param overrides an earlier one' => [
            ['@param list<int> $value', '@param list<string> $value'],
            'string',
        ];
        yield 'a @param for another parameter' => [['@param list<int> $value', '@param list<string> $other'], 'int'];
        yield '@phan-param overrides a later @param' => [
            ['@phan-param list<int> $value', '@param list<string> $value'],
            'int',
        ];
        yield '@psalm-param overrides @phan-param' => [
            ['@phan-param list<string> $value', '@psalm-param list<int> $value'],
            'int',
        ];
        yield '@phpstan-param overrides @psalm-param' => [
            ['@phpstan-param list<int> $value', '@psalm-param list<string> $value'],
            'int',
        ];
        yield 'a @phpstan-param for another parameter' => [
            ['@param list<int> $value', '@phpstan-param list<string> $other'],
            'int',
        ];
        yield 'a @param without a type' => [['@param list<int> $value', '@phpstan-param $value'], 'int'];
        yield 'a @param by reference' => [['@param list<int> &$value'], 'int'];
        yield 'a variadic @param' => [['@param list<int> ...$value'], 'int'];
        yield 'a variadic @param by reference' => [['@param list<int> &...$value'], 'int'];
        yield 'a @param with $this' => [['@param list<int> $value', '@phpstan-param list<string> $this'], 'int'];
        yield 'a @param without a variable' => [['@param list<int> $value', '@phpstan-param list<string>'], 'int'];
        yield 'a description' => [['@param list<int> $value The values.', ' More text.'], 'int'];
        yield 'text before the tags' => [['Summary.', '', '@param list<int> $value'], 'int'];
        yield 'a @var on a constructor' => [['@var list<int> $value'], null];
        yield 'an import before the tag' => [['@phpstan-import-type Foo from Bar', '@param list<int> $value'], 'int'];
        yield 'an aliased import' => [['@psalm-import-type Foo from Bar as Baz', '@param list<int> $value'], 'int'];
        yield 'text after an import' => [['@phpstan-import-type Foo from Bar text', '@param list<int> $value'], null];
        yield 'text after an aliased import' => [
            ['@phpstan-import-type Foo from Bar as Baz x', '@param list<int> $value'],
            null,
        ];
        yield 'an import without from' => [['@phpstan-import-type Foo of Bar x', '@param list<int> $value'], 'int'];
        yield 'an import without a class' => [['@phpstan-import-type Foo from', '@param list<int> $value'], 'int'];
        yield 'an import without an alias' => [
            ['@phpstan-import-type Foo from Bar as', '@param list<int> $value'],
            'int',
        ];
        yield 'an import without a name' => [['@phpstan-import-type', '@param list<int> $value'], 'int'];
        yield 'an import with a type' => [
            ['@phpstan-import-type list<int> from Bar', '@param list<int> $value'],
            'int',
        ];
        yield 'a type alias' => [['@phpstan-type Foo int', '@param list<int> $value'], 'int'];
        yield 'a type alias with =' => [['@psalm-type Foo = list<int>', '@param list<int> $value'], 'int'];
        yield 'a type alias with text' => [['@phan-type Foo = int text', '@param list<int> $value'], 'int'];
        yield 'a type alias without a type' => [['@phpstan-type Foo', '@param list<int> $value'], 'int'];
        yield 'a type alias without a name' => [['@phpstan-type = int', '@param list<int> $value'], 'int'];
        yield 'a type alias over lines' => [
            ['@phpstan-type Foo array{', '    int,', '}', '@param list<int> $value'],
            'int',
        ];
        yield 'a failed type alias after a comment in a shape' => [
            ['@psalm-type Foo = array{int, // c', '    !}', '@param list<int> $value'],
            'int',
        ];
        yield 'a failed type alias after a comment in generic arguments' => [
            ['@psalm-type Foo = list<int // c', '    >|', '@param list<int> $value'],
            'int',
        ];
        yield 'a tag that is not one' => [['@param-out list<string> $value', '@param list<int> $value'], 'int'];
        yield 'a tag in the middle of a line' => [['Text @param list<int> $value'], null];
        yield 'a tag after another tag on the line' => [['@return void @param list<int> $value'], null];
        yield 'a tag after an unknown tag on the line' => [['@foo @param list<int> $value'], null];
    }

    /**
     * Lines of a property's doc comment, and the item type of the list that phpdoc-parser reads for `$value`.
     *
     * @return iterable<string, array{list<string>, string|null}>
     */
    private static function properties(): iterable
    {
        yield 'an unnamed @var' => [['@var list<int>'], 'int'];
        yield 'a named @var' => [['@var list<int> $value'], 'int'];
        yield 'a @var named for another property' => [['@var list<int> $other'], null];
        yield 'a @var named $this' => [['@var list<int> $this'], null];
        yield 'an unnamed @var with a description' => [['@var list<int> The values.'], 'int'];
        yield 'an unnamed @var directly followed by text' => [['@var list<int>text'], null];
        yield 'an unnamed @var followed by a union operator' => [['@var list<int> |'], null];
        yield 'an unnamed @var followed by an intersection operator' => [['@var list<int> &'], null];
        yield 'a named @var directly followed by text' => [['@var list<int> $value.'], 'int'];
        yield 'two unnamed @var tags' => [['@var list<int>', '@var list<string>'], null];
        yield 'an unnamed @var overrides a named one' => [['@var list<string> $value', '@var list<int>'], 'int'];
        yield 'a later named @var overrides an earlier one' => [
            ['@var list<string> $value', '@var list<int> $value'],
            'int',
        ];
        yield 'an unnamed @var overrides a named @phpstan-var' => [
            ['@phpstan-var list<string> $value', '@var list<int>'],
            'int',
        ];
        yield 'an unnamed @phpstan-var overrides an unnamed @var' => [
            ['@phpstan-var list<int>', '@var list<string>'],
            'int',
        ];
        yield 'two unnamed @var tags and an unnamed @psalm-var' => [
            ['@var list<int>', '@var list<string>', '@psalm-var list<bool>'],
            'bool',
        ];
        yield 'an unnamed @var and a named @psalm-var' => [['@var list<int>', '@psalm-var list<string> $value'], 'int'];
        yield 'two unnamed @phan-var tags over one @var' => [
            ['@phan-var list<int>', '@phan-var list<string>', '@var list<bool>'],
            null,
        ];
        yield 'an unnamed @var and a later named @var' => [['@var list<int>', '@var list<string> $value'], 'int'];
        yield 'an unnamed @var with a variable on the next line' => [['@var list<int>', '$value'], 'int'];
        // An unnamed `@phpstan-var` overrides `@var` only if it is valid: a description must be separated from the
        // type by whitespace and must not continue it. A named one counts less than the unnamed `@var`.
        foreach ([
            'list<string> description' => 'string',
            'list<string>description' => 'int',
            'list<string>|description' => null,
            'list<string>&list<string> | x' => 'int',
            'list<string>|list<string> & x' => 'int',
            'list<string>|list<string> &$x' => null,
            'list<string> | x' => null,
            'list<string>,x' => 'int',
            'list<string> ,x' => 'string',
            'list<string>' => 'string',
            'list<string>  ' => 'string',
            'list<string> $value' => 'int',
            'list<string>$value' => 'int',
            'list<string> $this' => 'int',
            'list<string> $This' => 'int',
            'list<string> $thisValue' => 'int',
        ] as $value => $expected) {
            yield 'a @phpstan-var value ' . $value => [['@var list<int>', '@phpstan-var ' . $value], $expected];
        }
    }

    /**
     * Annotations before a `@param list<int>` tag, and whether phpdoc-parser can read the doc comment. It cannot if an
     * annotation that starts with a backslash or underscore has invalid arguments.
     *
     * @return iterable<string, bool>
     */
    private static function annotations(): iterable
    {
        yield '@\\Foo' => true;
        yield '@\\Foo text' => true;
        yield '@\\Foo()' => true;
        yield '@\\Foo(1)' => true;
        yield '@\\Foo(1, 2)' => true;
        yield '@\\Foo(1,)' => true;
        yield '@\\Foo(,)' => false;
        yield '@\\Foo(' => false;
        yield '@\\Foo(1' => false;
        yield '@\\Foo(1 2)' => false;
        yield '@\\Foo(a=1)' => true;
        yield '@\\Foo(a = 1, b = "x")' => true;
        yield '@\\Foo(a=)' => false;
        yield '@\\Foo(=1)' => false;
        yield '@\\Foo(a b)' => false;
        yield '@\\Foo(1.5, -1, 0x1F)' => true;
        yield '@\\Foo(true, false, null, TRUE)' => true;
        yield '@\\Foo(name)' => true;
        yield '@\\Foo("x")' => true;
        yield '@\\Foo("x" "y")' => true;
        yield '@\\Foo("a""b")' => true;
        yield "@\\Foo('x')" => false;
        yield "@\\Foo(a='x')" => false;
        yield '@\\Foo(Foo::BAR)' => true;
        yield '@\\Foo(Foo::class)' => true;
        yield '@\\Foo(Foo::BAR_*)' => true;
        yield '@\\Foo(\\Foo\\Bar::BAZ)' => true;
        yield '@\\Foo(Foo::)' => false;
        yield '@\\Foo(true::X)' => false;
        yield '@\\Foo({})' => true;
        yield '@\\Foo({1, 2})' => true;
        yield '@\\Foo({1,})' => true;
        yield '@\\Foo({,})' => false;
        yield '@\\Foo({1 2})' => false;
        yield '@\\Foo({"a": 1})' => true;
        yield '@\\Foo({"a" = 1})' => true;
        yield '@\\Foo({a: 1})' => true;
        yield '@\\Foo({a = 1, b: {c = 2}})' => true;
        yield '@\\Foo({1: 2})' => true;
        yield "@\\Foo({'a': 1})" => true;
        yield "@\\Foo({'a'})" => false;
        yield '@\\Foo({Foo::BAR = 1})' => true;
        yield '@\\Foo({true::X = 1})' => false;
        yield '@\\Foo({"a""b": 1})' => true;
        yield '@\\Foo({"a" "b": 1})' => true;
        yield '@\\Foo({a})' => true;
        yield '@\\Foo({a:})' => false;
        yield '@\\Foo({a = })' => false;
        yield "@\\Foo({\"a\n * b\": 1})" => false;
        yield "@\\Foo({\"a\n * b\" : : 1})" => true;
        yield '@\\Foo(@\\Bar)' => true;
        yield '@\\Foo(@Bar)' => true;
        yield '@\\Foo(@\\Bar(1))' => true;
        yield '@\\Foo(a=@Bar(b=2))' => true;
        yield '@\\Foo({@Bar, @Baz({1})})' => true;
        yield '@\\Foo(@Bar()' => false;
        yield '@\\Foo(@Bar(,))' => false;
        yield '@\\Foo(array(1, 2))' => false;
        yield '@\\Foo([1, 2])' => false;
        yield '@\\Foo([1, 2,])' => false;
        yield '@\\Foo([1 2])' => false;
        yield '@\\Foo([a => 1])' => false;
        yield '@\\Foo([a => ])' => false;
        yield "@\\Foo(\n *     a=1,\n *     b=2\n * )" => true;
        yield "@\\Foo(\n *     a=1,\n * " => false;
        yield "@\\Foo(a=\"x\n * y\")" => true;
        yield "@\\Foo({\n *     \"a\": 1,\n * })" => true;
        yield '@\\Foo(1) text after' => true;
        yield '@\\Foo(1)text' => true;
        yield '@\\Foo(1) @\\Bar(' => false;
        yield '@\\Foo(1) @Bar(' => true;
        yield '@\\Foo(1)@\\Bar(' => true;
        yield '@\\Foo @bar @\\Baz(' => false;
        yield '@\\Foo @param @\\Baz(' => true;
        yield '@\\Foo @param int $x @\\Baz(' => true;
        yield '@foo(' => true;
        yield '@foo @\\Bar(' => false;
        yield '@foo @bar(x) @\\Baz(' => false;
        yield '@foo @return(int) @\\Baz(' => true;
        yield '@foo @return @\\Baz(' => true;
        yield '@foo @param( @\\Baz(' => true;
        yield '@Foo\\Bar(' => true;
        yield '@_foo(' => false;
        yield '@_foo(1)' => true;
        yield '@a:b(' => true;
        yield '@ab:cd(' => true;
        yield '@ab:cd @\\X(' => false;
    }

    /**
     * Types of a `@phpstan-param` tag that overrides `@param list<int>`, and whether phpdoc-parser reads them as a type
     * followed by the variable.
     *
     * @return iterable<string, bool>
     */
    private static function overridingTypes(): iterable
    {
        yield '?int' => true;
        yield '? int' => true;
        yield 'int[]' => true;
        yield 'list<int>[]' => true;
        yield 'list<int>[int]' => true;
        yield "Foo['a']" => true;
        yield 'Foo[Bar::BAZ]' => true;
        yield 'int[][]' => true;
        yield 'int []' => true;
        yield 'int [int]' => false;
        yield 'int[' => false;
        yield 'int[int' => false;
        yield '(int)[]' => true;
        yield '$this' => true;
        yield '$this[]' => true;
        yield '$This' => true;
        yield '$thisValue' => false;
        yield 'callable' => true;
        yield 'callable()' => false;
        yield 'callable(): void' => true;
        yield 'callable() : void' => true;
        yield 'callable(int): void' => true;
        yield 'callable(int,): void' => true;
        yield 'callable(int, string): void' => true;
        yield 'callable(int $a, string ...$rest): void' => true;
        yield 'callable(int &$a): void' => true;
        yield 'callable(int &...$a): void' => true;
        yield 'callable(int=): void' => true;
        yield 'callable(int $a=): void' => true;
        yield 'callable($a): void' => false;
        yield 'callable(int, ,): void' => false;
        yield 'callable(int): ?int' => true;
        yield 'callable(): (int|string)' => true;
        yield 'callable(): int|string' => true;
        yield 'callable(): list<int>' => true;
        yield 'callable(): array{int}' => true;
        yield 'callable(): object{a: int}' => true;
        yield 'callable(): int[]' => true;
        yield 'callable(): $this' => true;
        yield 'callable(): $this[]' => true;
        yield 'callable(): Foo::BAR' => true;
        yield 'callable(): Foo::BAR[]' => true;
        yield "callable(): 'a'" => true;
        yield 'callable(): static' => true;
        yield 'callable(): callable(): int' => false;
        yield 'callable(): (callable(): int)' => true;
        yield 'callable(): ' => false;
        yield 'callable<T>(T): T' => true;
        yield 'callable<T of int, U = string>(T): U' => true;
        yield 'callable<T super int>(T): T' => true;
        yield 'callable<T as int>(T): T' => true;
        yield 'callable<T,>(T): T' => true;
        yield 'callable<>(): int' => false;
        yield '\\Closure(int): void' => true;
        yield 'Closure<T>(T): void' => true;
        yield "callable(\n *     int,\n *     string,\n * ): void" => true;
        yield "callable<\n *     T,\n * >(T): void" => true;
        yield 'pure-callable(int): void' => true;
        yield '(T is int ? string : bool)' => true;
        yield '(T is not int ? string : bool)' => true;
        yield '($value is int ? string : bool)' => true;
        yield "(\$value is int\n *     ? string\n *     : bool)" => true;
        yield '($value is int ? string)' => false;
        yield '($value ? string : bool)' => false;
        yield '(T is int ? string : (U is int ? int : bool))' => true;
        yield 'object{}' => true;
        yield 'object{a: int}' => true;
        yield "object{'a'?: int, \"b\": string,}" => true;
        yield "object{\n *     a: int,\n * }" => true;
        yield 'object{a: int' => false;
        yield 'object{a}' => false;
        yield 'object{0: int}' => false;
        yield 'object{a: int}[]' => true;
        yield 'object {a: int}' => false;
        yield 'array{int, ...}' => true;
        yield 'array{...}' => true;
        yield 'array{int, ...<string>}' => true;
        yield 'array{int, ...<int, string>}' => true;
        yield 'list{int, ...<string>}' => true;
        yield 'list{int, ...<int, string>}' => false;
        yield 'array{int, ...,}' => true;
        yield "array{int, ...<\n *     int,\n *     string\n * >}" => true;
        yield 'array{int, ... }' => true;
        yield 'array{int, ...<string>, int}' => false;
        yield 'array{int}[]' => true;
        yield 'list<*>' => true;
        yield 'list<covariant int>' => true;
        yield 'list<contravariant int>' => true;
        yield 'list<int, *>' => true;
        yield 'Foo<covariant>' => false;
        yield 'int<0, max>[]' => true;
        yield 'Foo::BAR' => true;
        yield 'Foo::' => false;
        yield 'Foo::BAR::BAZ' => false;
        yield 'Foo::**' => false;
        yield 'Foo::*[]' => true;
        yield 'array(1, 2)' => false;
        yield '[1, 2]' => false;
        yield 'true::FOO' => false;
        yield 'int&' => true;
        yield 'int|' => false;
        yield '|int' => false;
        yield 'int|?string' => false;
        yield 'int|(string)' => true;
        yield '(int|string)[]' => true;
        yield '(int|string' => false;
        yield '()' => false;
        yield 'list<>' => false;
        yield 'list<int' => false;
        yield 'array{' => false;
        yield 'array{int' => false;
        yield 'array{0: }' => false;
        yield 'Foo<b>' => true;
        yield '!int' => false;
        yield '1.5' => true;
        yield '"a" . "b"' => false;
    }

    /**
     * @param list<string> $lines
     * @param string|null $expected The item type of the list that is read, or null if none is.
     * @param 'param'|'var' $tag
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    private static function read(string $label, array $lines, string|null $expected, string $tag = 'param'): iterable
    {
        yield from self::document($label, "/**\n * " . implode("\n * ", $lines) . "\n */", $expected, $tag);
    }

    /**
     * Decodes `[{}]` with a whole doc comment on an `array` field named `value`.
     *
     * @param string|null $expected The item type of the list that is read, or null if none is.
     * @param 'param'|'var' $tag
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function document(string $label, string $doc, string|null $expected, string $tag): iterable
    {
        $class = CollectionDeclarationFixture::create('array', $doc, $tag);
        yield 'PHPDoc tags: ' . $label . ' in ' . $tag => [
            '{"value":[{}]}',
            $class,
            $expected === null
                ? 'Could not create '
                . $class
                . ' from the JSON object: '
                . UnsupportedCollection::unrecognized('value', $tag)->getMessage()
                : 'Could not create '
                . $class
                . ' from the JSON object: Field value[0] must be of type '
                . $expected
                . ', stdClass given.',
            $expected === null ? DecodeErrorKind::UnsupportedDeclaration->value : DecodeErrorKind::InvalidValue->value,
        ];
    }
}
