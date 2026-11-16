<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Closure;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Internal\DiscriminatorInference;
use Eventjet\Json\Internal\EnumRoots;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\NodeCompiler;
use Eventjet\Json\Internal\NullableJsonValues;
use Eventjet\Json\Internal\PhpDocCallableParser;
use Eventjet\Json\Internal\PhpDocHtmlDetector;
use Eventjet\Json\Internal\PhpDocIntegerRange;
use Eventjet\Json\Internal\PhpDocLexer;
use Eventjet\Json\Internal\PhpDocSimpleTags;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Test\Acceptance\Fixtures\Address;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ClassScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Acceptance\Fixtures\DeferredListItem;
use Eventjet\Json\Test\Acceptance\Fixtures\DeferredListItemPart;
use Eventjet\Json\Test\Acceptance\Fixtures\DeferredValueEnum;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use PHPUnit\Framework\Assert;
use ReflectionProperty;
use stdClass;

use function array_keys;
use function is_object;
use function json_encode;
use function property_exists;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Decoding loads only the classes that the declarations and values need. Each case is built in the process that runs
 * it, because PHPUnit passes data sets to separate processes serialized.
 *
 * @internal
 */
final class LoadingCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string}>
     */
    public static function names(): iterable
    {
        foreach (array_keys(self::cases()) as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return list<LoadingStep>
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function steps(string $name): array
    {
        $case = self::cases()[$name] ?? null;
        return $case === null ? [] : $case();
    }

    /** @return array<string, Closure(): list<LoadingStep>> */
    private static function cases(): array
    {
        return [
            'empty object lists do not compile their item classes' => self::emptyLists(...),
            'built-in fields never call autoloaders' => self::builtinFields(...),
            'literal fields never call autoloaders' => self::literalFields(...),
            'enum backing constants load only for matching input types' => self::enumBackings(...),
            'native class and enum fields skip the union compiler' => self::nativeFields(...),
            'doc comments load only the parsers they need' => self::docComments(...),
        ];
    }

    /**
     * @return list<LoadingStep>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function emptyLists(): array
    {
        $items = CollectionDeclarationFixture::object('array', 'list<\\' . DeferredListItem::class . '>', 'var', []);
        return [new LoadingStep([['{"value":[]}', $items::class, LoadingStep::equals($items)]], unloaded: [
            DeferredListItemPart::class,
        ])];
    }

    /** @return list<LoadingStep> */
    private static function builtinFields(): array
    {
        $target = new class {
            public function __construct(
                public int $integer = 1,
                public float $float = 1.5,
                public string $string = 'value',
                public bool $boolean = true,
                public int|null $nullable = null,
            ) {}
        };
        // A doc comment that does not narrow an unsupported native type leaves the native type in charge.
        $imprecise = new class {
            /** @var stdClass */
            public object $value;

            public function __construct()
            {
                $this->value = new stdClass();
            }
        };
        return [new LoadingStep([
            [
                '{"integer":1,"float":1.5,"string":"value","boolean":true,"nullable":null}',
                $target::class,
                LoadingStep::equals($target),
            ],
            ['{"value":{}}', $imprecise::class, LoadingStep::fails(DecodeErrorKind::UnsupportedDeclaration)],
        ], libraryOnly: true)];
    }

    /**
     * @return list<LoadingStep>
     * @throws \JsonException
     */
    private static function literalFields(): array
    {
        $target = new class('a', 1, true) {
            /** @var \Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields::NUMBER */
            public int $constant = ExtendedLiteralFields::NUMBER;

            /** @var list<bool> */
            public array $booleans = [true];

            /** @var list<float> */
            public array $floats = [1.5];

            /** @var list<int> */
            public array $integers = [1];

            /** @var list<string> */
            public array $strings = ['value'];

            /** @var list<42> */
            public array $literals = [42];

            /**
             * @param 'a'|"b" $string
             * @param 1|-7 $integer
             * @param true $boolean
             */
            public function __construct(
                public string $string,
                public int $integer,
                public bool $boolean,
            ) {}
        };
        return [new LoadingStep([[
            json_encode($target, JSON_THROW_ON_ERROR),
            $target::class,
            LoadingStep::equals($target),
        ]], libraryOnly: true)];
    }

    /** @return list<LoadingStep> */
    private static function enumBackings(): array
    {
        $field = CollectionDeclarationFixture::create('\\' . DeferredValueEnum::class, '', 'param');
        $isReady =
            /** @throws \ReflectionException */
            static function (mixed $decoded): void {
                Assert::assertTrue(is_object($decoded) && property_exists($decoded, 'value'));
                Assert::assertSame(
                    DeferredValueEnum::Ready,
                    new ReflectionProperty($decoded, 'value')->getValue($decoded),
                );
            };
        return [
            new LoadingStep([['{"value":1}', $field, LoadingStep::fails(DecodeErrorKind::InvalidValue)]], unloaded: [
                DeferredEnumBacking::class,
            ]),
            new LoadingStep([['{"value":"ready"}', $field, $isReady]], loaded: [DeferredEnumBacking::class]),
        ];
    }

    /**
     * Native class and enum fields compile without the type tree and union that NodeCompiler builds. A union with
     * one class compiles without discriminator support.
     *
     * @return list<LoadingStep>
     * @throws \JsonException
     */
    private static function nativeFields(): array
    {
        $person = new Person('Ada', 'Lovelace');
        $address = new Address('London', new Coordinates(51.5, -0.1));
        return [
            new LoadingStep(
                LoadingStep::roundTrips([
                    new BackedEnumFields(StringBackedStatus::Ready, null, IntBackedStatus::Ready),
                    new BackedEnumFields(
                        StringBackedStatus::Ready,
                        StringBackedStatus::Pending,
                        IntBackedStatus::Ready,
                    ),
                    new NestedObjectFields($person, $address, null),
                    new NestedObjectFields($person, $address, $person),
                ]),
                unloaded: [
                    NodeCompiler::class,
                    UnionBuilder::class,
                    NullOnlyNode::class,
                    EnumRoots::class,
                    NullableJsonValues::class,
                ],
            ),
            new LoadingStep(
                LoadingStep::roundTrips([new ClassScalarUnionField($person), new ClassScalarUnionField(42)]),
                unloaded: [DiscriminatorInference::class],
                loaded: [UnionBuilder::class],
            ),
        ];
    }

    /**
     * Doc comments without tags are not read. Doc comments with only `@param` and `@var` tags whose types fit on one
     * line are read without the full PHPDoc parser. The full parser looks for HTML and callables only where they can
     * follow: an HTML end tag needs a slash after the opening, and a callable needs a later `(`.
     *
     * @return list<LoadingStep>
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    private static function docComments(): array
    {
        $untagged = new class([1]) {
            /** Takes 1 list. */
            public function __construct(
                /** @var list<int> */
                public array $items,
            ) {}
        };
        $number = '\\' . ExtendedLiteralFields::class . '::NUMBER';
        $status = '\\' . StringBackedStatus::class;
        $nested = [1];
        for ($level = 0; $level < 62; ++$level) {
            $nested = [$nested];
        }
        return [
            new LoadingStep([[
                '{"items":[1]}',
                $untagged::class,
                LoadingStep::fails(DecodeErrorKind::UnsupportedDeclaration),
            ]], unloaded: [
                PhpDocSimpleTags::class,
            ]),
            new LoadingStep(LoadingStep::roundTrips([
                CollectionDeclarationFixture::object('array', '/** @param list<int> $value */', 'param', [1]),
                CollectionDeclarationFixture::object('array', "/**\n * @param list<int> \$value\n */", 'param', [1]),
                CollectionDeclarationFixture::object('array', "/**\r\n * @var list<string>\r\n */", 'var', ['a']),
                CollectionDeclarationFixture::object(
                    'array',
                    "/**\n\t * @phpstan-param list<string> \$value Text.\n\t * More text.\n\t */",
                    'param',
                    ['a'],
                ),
                CollectionDeclarationFixture::object(
                    'array',
                    "/**\n * Summary.\n *\n * @psalm-var non-empty-list<'a'|\"b\"> \$value\n */",
                    'var',
                    ['b'],
                ),
                CollectionDeclarationFixture::object('?array', '/** @phan-var list<int>|null */', 'var', null),
                CollectionDeclarationFixture::object(
                    'array',
                    '/** @var list<' . $number . '|-1|0> $value */',
                    'var',
                    [42, -1],
                ),
                CollectionDeclarationFixture::object(
                    'ArrayObject',
                    '/** @param ArrayObject < string , int > $value */',
                    'param',
                    new ArrayObject(['a' => 1]),
                ),
                CollectionDeclarationFixture::object('array', "/**\n   @param list< int > \$value\n */", 'param', [1]),
                CollectionDeclarationFixture::object('array', "/**\n *@var list<int>\n **/", 'var', [1]),
                CollectionDeclarationFixture::object('array', '/**  @var  list<int>  */', 'var', [1]),
                CollectionDeclarationFixture::object(
                    'array',
                    "/**\n * @param list<int> \$other\n * @param list<" . $status . "> \$value\n */",
                    'param',
                    [StringBackedStatus::Ready],
                ),
                CollectionDeclarationFixture::object(
                    'array',
                    "/**\n * @var list<int>|list<string> \$other A | B\n * @var list<bool> \$value\n */",
                    'var',
                    [true],
                ),
                CollectionDeclarationFixture::object(
                    'array',
                    '/** @var non-empty-array<string, list<int>> */',
                    'var',
                    ['a' => [1]],
                ),
                CollectionDeclarationFixture::object('array', '/** @param list<int>    $value  */', 'param', [1]),
                CollectionDeclarationFixture::object(
                    'array',
                    "/**\n * {@inheritDoc}\n * @param list<int> \$value\n */",
                    'param',
                    [1],
                ),
                CollectionDeclarationFixture::object(
                    'array',
                    '/** @param ' . str_repeat('list<', times: 63) . 'int' . str_repeat('>', times: 63) . ' $value */',
                    'param',
                    $nested,
                ),
            ]), unloaded: [PhpDocLexer::class, PhpDocIntegerRange::class]),
            new LoadingStep(
                LoadingStep::roundTrips([
                    CollectionDeclarationFixture::object(
                        'array',
                        "/**\n * @return void\n * @param list<int> \$value\n */",
                        'param',
                        [1],
                    ),
                    CollectionDeclarationFixture::object(
                        'array',
                        "/**\n * @param list<array{int}> \$value\n * @param-out int \$value\n */",
                        'param',
                        [[1]],
                    ),
                    CollectionDeclarationFixture::object(
                        'array',
                        "/**\n * (Text.)\n * @return void\n * @param list<int> \$value\n */",
                        'param',
                        [1],
                    ),
                ]),
                unloaded: [PhpDocHtmlDetector::class, PhpDocCallableParser::class],
                loaded: [PhpDocLexer::class],
            ),
        ];
    }
}
