<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

/**
 * Builds tags other than `@param` and `@var`, whose values decide where the next tag starts.
 *
 * @internal
 */
final readonly class OtherTags
{
    private const array TAGS = [
        '@return',
        '@throws',
        '@template',
        '@template-covariant',
        '@method',
        '@property',
        '@property-read',
        '@phpstan-type',
        '@psalm-type',
        '@phpstan-import-type',
        '@psalm-import-type',
        '@phpstan-assert',
        '@psalm-assert-if-true',
        '@extends',
        '@implements',
        '@use',
        '@deprecated',
        '@param-out',
        '@phpstan-self-out',
        '@param-closure-this',
        '@param-immediately-invoked-callable',
        '@pure-unless-parameter-passed',
        '@mixin',
        '@phpstan-sealed',
        '@see',
        '@internal',
        '@api',
        '@author',
        '@link',
        '@inheritDoc',
        '@mago-expect',
        '@ORM\Column',
        '@Param',
        '@param-later-invoked-callable',
        '@phpstan-require-extends',
        '@phan-return',
        '@psalm-import-type',
    ];

    private const array METHOD_PARAMETERS = [
        '',
        'int $a',
        'int $a = [1, 2]',
        '$a = [1 => 2, "b" => [3]]',
        'int $a = Foo::BAR',
        '$a = array(1)',
        '?int ...$a, string &$b = "x"',
    ];

    private const array ANNOTATION_ARGUMENTS = [
        '',
        '(',
        '()',
        '(name="id")',
        "(name=\"id\",\n *     type=\"int\")",
        '(type={1, "a": 2, Foo::BAR = 3})',
        '(@Foo, @Bar(1))',
        '(name="x"',
        "(\n * @var(int)\n * )",
        '("a""b")',
        "(\"multi\n * line\")",
        '({"k" = 1})',
        '(true::X = 1)',
        '(x=array(1))',
        '([1])',
        '({true::X = 1})',
        '({null::X: 1})',
        "({\"a\n * b\" = 1})",
        '({"a""b" = 1})',
        '({"a" "b" = 1})',
        '(x=[1 => 2, 3])',
        '({Foo::BAR: [1 => 2]})',
    ];

    private const array DESCRIPTIONS = [
        '',
        ' text',
        ' Name <mail@example.com>',
        ' @param int $value',
        ' @foo bar',
        ' @foo(',
        ' @param(int) $value',
        ' @return(',
        ' @\Foo(',
        ' {@see Foo}',
        ' x@y',
    ];

    public function __construct(
        private Dice $dice,
        private GrammarTypes $types,
    ) {}

    public function tag(): string
    {
        $tag = $this->dice->pick(self::TAGS);
        return $tag . match ($tag) {
            '@method' => $this->method(),
            '@template', '@template-covariant' => ' T'
                . $this->dice->pick([
                    '',
                    ' of ' . $this->types->type(),
                    ' as int',
                    ' = ' . $this->types->type(),
                    ' super int',
                ])
                . $this->dice->pick(['', ' desc', '|x']),
            '@phpstan-type', '@psalm-type' => ' '
                . $this->dice->pick(['Foo', 'Foo =', ''])
                . ' '
                . $this->types->type()
                . $this->dice->pick(['', ' trailing', "\n * @param int \$value"]),
            '@phpstan-import-type', '@psalm-import-type' => ' '
                . $this->dice->pick([
                    'Foo from Bar',
                    'Foo from Bar as Baz',
                    'Foo from',
                    'Foo Bar',
                    'Foo from Bar trailing',
                    'Foo from Bar as',
                ]),
            '@phpstan-assert', '@psalm-assert-if-true' => ' '
                . $this->dice->pick(['', '!', '='])
                . $this->types->type()
                . ' '
                . $this->dice->pick(['$x', '$this', '$x->foo()', '$x->bar', ''])
                . $this->dice->pick(['', ' desc']),
            '@extends', '@implements', '@use' => ' '
                . $this->dice->pick(['Foo<int>', 'Foo', 'Foo<int', 'Foo<T> desc', 'Foo<T>|x']),
            '@ORM\Column' => $this->dice->pick(self::ANNOTATION_ARGUMENTS)
                . $this->dice->pick(['', ' desc', ' @Bar(1)', ' @param int $value', ' @foo']),
            '@see',
            '@internal',
            '@api',
            '@author',
            '@link',
            '@inheritDoc',
            '@mago-expect',
            '@Param',
                => $this->dice->pick(self::DESCRIPTIONS),
            default => ' '
                . $this->dice->pick([
                    '',
                    $this->types->type(),
                    $this->types->type() . ' $x',
                    $this->types->type() . ' desc',
                    '$x desc',
                    $this->types->type() . '|x',
                ]),
        };
    }

    private function method(): string
    {
        return (
            ' '
            . $this->dice->pick(['', 'static ', 'int ', 'static int '])
            . $this->dice->pick(['foo', 'static', 'list<int>', 'true'])
            . $this->dice->pick(['', '<T>', '<T of int, U>'])
            . '('
            . $this->dice->pick(self::METHOD_PARAMETERS)
            . ')'
            . $this->dice->pick(['', ' desc'])
        );
    }
}
