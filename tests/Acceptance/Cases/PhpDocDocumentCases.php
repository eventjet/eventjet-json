<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

use function json_encode;

use const JSON_UNESCAPED_SLASHES;

/**
 * Small doc comments that phpstan/phpdoc-parser reads in a particular way, where a parser that is almost the same would
 * read them differently. See {@see PhpDocTagCases::document()} for how each is read.
 *
 * A type alias such as `@psalm-type A = (//` that fails after a comment keeps the comment, which makes the doc comment
 * unreadable unless a later complete type takes it. phpdoc-parser checks this, so it tells whether a later type parses.
 *
 * @internal
 */
final class PhpDocDocumentCases
{
    /** Doc comments, the tag they declare `value` with, and the item type of the list that is read, or null if none is. */
    private const array DOCUMENTS = [
        [
            "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @method foo(\$a = array(1)::X)\n */",
            'param',
            null,
        ],
        ["/**\n * @param list<string> \$value\n * @param callable(): Foo::BAR<int> \$value\n */", 'param', 'string'],
        [
            "/**\n * @param list<string> \$value\n * @param callable(): array<int>{a: int} \$value\n */",
            'param',
            'string',
        ],
        [
            "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @Foo(array,\n * @var(int)\n * )\n */",
            'param',
            null,
        ],
        [
            "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @foo text @bar(a=1,\n * @var(int)\n * )\n */",
            'param',
            null,
        ],
        ["/**\n * @param list<string> \$value\n * @param list<1> \$value </1>\n */", 'param', '1'],
        ["/**\n * @param array{int(): int} \$value\n */", 'param', null],
        ["/**\n * @param list<string> \$value\n * @param (\$value foo int ? A : B) \$value\n */", 'param', 'string'],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @method (Foo)()\n */", 'param', 'int'],
        [
            "/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @method static (Foo)()\n */",
            'param',
            'int',
        ],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @method (Foo|Bar)()\n */", 'param', null],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = (//\n * !\n * @method 'a'()\n */", 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@phpstan-var array{a,//\n'':o():p*/", 'var', 'int'],
        ["/**\n * @param list<int> \$value\n * @psalm-type A(//\n!\n@phpstan-var o::**/", 'param', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=[)*/", 'var', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method i(\$a=[1=>])*/",
            'var',
            null,
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=[1])*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-var f::* list<string>*/", 'var', null],
        ["/**\n * @param list<int> \$value\n * @a\n** @_o(\n@phpstan-var l*/", 'param', 'int'],
        ["/** @phpstan-param t<int<o>>\$value</int>\n * @param list<int> \$value\n */", 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@return i*/", 'var', 'int'],
        ["/** @param l\$value\n@psalm-type A(//\n * @param list<int> \$value\n */", 'param', null],
        ["/**\n * @param list<int> \$value\n *\rc\n@phpstan-var i*/", 'param', null],
        ["/**\n * @param list<int> \$value\n * @phpstan-param > \$value*/", 'param', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method t\$x*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@deprecated t\$x*/", 'var', null],
        ["/**\n * @var list<int>\n * @m(\n@phpstan-var(n) )*/", 'var', 'int'],
        ["/**\n * @param list<int> \$value\n * @o @t@phpstan-param t\$value*/", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n *\n\n@\\o(\n@phpstan-var 9*/", 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phpstan-param T|x*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(...\$a)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=)*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o()*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o<T>()*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@method n o<T>()*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@method n o<T()*/", 'var', null],
        ['/** @template*/', 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@extends<t>*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phan-assert!t\$x*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phan-assert i*/", 'var', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@phan-assert i\$x->f*/",
            'var',
            'int',
        ],
        ["/** @param list<string>\$value\n@psalm-type A(//\n!\n@var\$this*/", 'param', 'string'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method t[]*/", 'var', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@phpstan-param s():f::u*/",
            'var',
            null,
        ],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method i(\$a=[2,])*/",
            'var',
            'int',
        ],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method i(\$a=[1[]])*/",
            'var',
            null,
        ],
        ["/** @s(\n@\\r({x\n@param l\$value\n * @param list<int> \$value\n */", 'param', null],
        ["/**\n * @var list<int>\n * @m(/\n@phpstan-var(n)\n/*/", 'var', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@param-closure-this i\$x*/",
            'var',
            'int',
        ],
        ["/** @param s\$value\n@return i*\n * @param list<int> \$value\n */", 'param', 'int'],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@param-immediately-invoked-callable i\$x*/",
            'var',
            null,
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@template t\$x*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@x*/", 'var', 'int'],
        ["/**\n * @param list<int> \$value\n * @phpstan-var v\n@t*/", 'param', 'int'],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@phpstan-param t<o():[]>\$e*/",
            'var',
            null,
        ],
        ['/** @method o(*/', 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(a)*/", 'var', null],
        ['/** @method*/', 'param', null],
        ["/** @param s\$value\n@method o()*\n * @param list<int> \$value\n */", 'param', 'int'],
        [
            "/**\n * @param list<int> \$value\n * @param-immediately-invoked-callable\$x c\n@phpstan-var 7*/",
            'param',
            'int',
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@template T of i*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@extends o<t>*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phan-assert=t\$x*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phan-assert t\$x*/", 'var', 'int'],
        [
            "/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@phan-assert i\$x->o(*/",
            'var',
            null,
        ],
        [
            "/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@phpstan-param e():i*/",
            'var',
            'int',
        ],
        ["/** @param l\$value\n@psalm-type A(?5//\n * @param list<int> \$value\n */", 'param', null],
        ["/**\n * @param list<int> \$value\n * @phpstan-param\tTRUE::_\$value*/", 'param', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=[1)*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-var r&c*/", 'var', null],
        ["/**\n * @var list<int>\n * @m(\n@phpstan-var(n) ) @u*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phpstan-param i*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A y<//\n***/", 'var', 'int'],
        [
            "/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phpstan-param array{((f is F\to:j))}...\$o*/",
            'var',
            null,
        ],
        [
            "/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@param-closure-this i*/",
            'var',
            null,
        ],
        ["/** @param l\$value\n@psalm-type(//\n * @param list<int> \$value\n */", 'param', 'int'],
        ["/** @var list<string>\n|list<string>*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//*/", 'var', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@param-closure-this i\$this*/",
            'var',
            null,
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@extends t\$x*/", 'var', null],
        ["/**\n * @param list<int> \$value\n * @i @k@phpstan-param i\$value\nl*/", 'param', 'int'],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method o(\$a=array))*/",
            'var',
            null,
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(t\$a)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method t o()*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o)*/", 'var', null],
        ["/** @param l\$value\n@psalm-type A(//\n?i\n * @param list<int> \$value\n */", 'param', null],
        ['/** @deprecated*/', 'param', null],
        ["/** @param s\$value\n@template T o\n * @param list<int> \$value\n */", 'param', 'int'],
        ["/** @param s\$value\n@extends o<t>*\n * @param list<int> \$value\n */", 'param', 'int'],
        ["/** @param l\$value\n@phan-assert i\$x*\n * @param list<int> \$value\n */", 'param', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(?5//\n)*/", 'var', 'int'],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method i(\$a=[\"\"=>[]])*/",
            'var',
            'int',
        ],
        [
            "/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@D @phpstan-param t\$v*/",
            'var',
            null,
        ],
        ["/** @s(\n@\\r({x=\n@param l\$value\n * @param list<int> \$value\n */", 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param list{//\n}\$value*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(\"\"//*/", 'var', 'int'],
        ["/** @param l\$value\n@param-closure-this i\$x*\n * @param list<int> \$value\n */", 'param', 'int'],
        ["/** @param l\$value\n@psalm-type A=(//\n * @param list<int> \$value\n */", 'param', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@phpstan-param(array{0:v*/",
            'var',
            null,
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A object{//\nt*/", 'var', 'int'],
        ["/** @param l\$value\n@psalm-type o e\nn\n * @param list<int> \$value\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n *\n@phpstan-param t\$x\n\n*/", 'param', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@phpstan-var t|f|*/", 'var', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method o(\$a=array(1))*/",
            'var',
            'int',
        ],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(?t\$a)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(&\$a)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=1)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@method n o<>()*/", 'var', null],
        ['/** @param-immediately-invoked-callable*/', 'param', null],
        ["/**\n * @param list<int> \$value\n * @deprecated c\n@phpstan-param 3\$Value*/", 'param', 'int'],
        ['/** @extends*/', 'param', null],
        ['/** @phpstan-assert*/', 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param l\$value\n@psalm-type A(//\n!\n@phan-assert i\$x->*/", 'var', null],
        ["/** @param list<string>\$value\n@param array{...<list<string>>\n,}\$value*/", 'param', null],
        ["/** @param list<string>\$value\n@param(T is t?A:\nB)\$value*/", 'param', null],
        [
            "/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=[2,])*/",
            'var',
            'int',
        ],
        ["/** @param list<string>\$value\n@param array{...<list<string>\n>}\$value*/", 'param', null],
        ["/** @param list<string>\$value\n@param(T is t?\nA:B)\$value*/", 'param', null],
        [
            "/** @param s\$value\n@psalm-param object{a:t\n,'':n}\$value\n * @param list<int> \$value\n */",
            'param',
            null,
        ],
        ["/** @param list<string>\$value\n@param e(list<string>\n,):d\$value*/", 'param', null],
        ["/** @param list<string>\$value\n@param array{...,\n}\$value*/", 'param', null],
        ["/** @param list<string>\$value\n@param array{...\n<list<string>>}\$value*/", 'param', null],
        ["/**\n * @var list<int>\n * @phpstan-param s\$value\n@psalm-type A(//\n!\n@method o(\$a=X)*/", 'var', 'int'],
        ["/**\n * @var list<int>\n * @o({.5:1},\n@phpstan-var(n) )*/", 'var', null],
        ["/**\n * @var list<int>\n * @phpstan-param i\$value\n@psalm-type A(//\n!\n@method s(\$a*/", 'var', null],
        ["/**\n * @param list<int(): int> \$value\n */", 'param', null],
        ["/** @param list<int> \$value*/", 'param', 'int'],
        ["/**\n * @param list<string> \$value\n * @param Closure<T\n * ,>(): T \$value\n */", 'param', null],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = Foo<int, // c\n * *,\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = int|list{ // c\n * 1: e(): o\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n * @return int // c\n * |'a'\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n * @return int // c\n * |\$this\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n * @return int|list{int // c\n * }\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = Foo<1 // c\n */", 'param', 'int'],
        ["/**\n * @param list<int> \$value\n * @psalm-type A = (?int[] // c\n * )|\n */", 'param', 'int'],
    ];

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::DOCUMENTS as [$doc, $tag, $expected]) {
            yield from PhpDocTagCases::document(
                (string) json_encode($doc, JSON_UNESCAPED_SLASHES),
                $doc,
                $expected,
                $tag,
            );
        }
    }
}
