<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use BackedEnum;
use DateTimeImmutable;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\CollectionCompiler;
use Eventjet\Json\Internal\CollectionUnionCompiler;
use Eventjet\Json\Internal\CollectionUnionNatives;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\LiteralFieldCompiler;
use Eventjet\Json\Internal\NativeUnionCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarItems;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UnionNode;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocCollectionCompiler;
use Eventjet\Json\Internal\PhpDocConstantImportStatement;
use Eventjet\Json\Internal\PhpDocConstantResolver;
use Eventjet\Json\Internal\PhpDocConstantValues;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocItemCompiler;
use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocLiteralNumber;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocUnionCompiler;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use const INF;
use const NAN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(PhpDocLiteralNumber::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocNumbers::class)]
#[CoversClass(PhpDocConstantValues::class)]
#[CoversClass(ValueTypeMatcher::class)]
#[CoversClass(LiteralFieldCompiler::class)]
#[CoversClass(LiteralNode::class)]
#[CoversClass(PhpDocFieldType::class)]
#[CoversClass(PhpDocType::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(PhpDocImportStatement::class)]
#[CoversClass(PhpDocConstantImportStatement::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(CollectionCompiler::class)]
#[UsesClass(CollectionUnionCompiler::class)]
#[UsesClass(CollectionUnionNatives::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(UnsupportedField::class)]
#[UsesClass(UnsupportedFieldClass::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NativeUnionCompiler::class)]
#[UsesClass(ObjectNode::class)]
#[UsesClass(PhpDocClassNameResolver::class)]
#[UsesClass(PhpDocCollectionCompiler::class)]
#[UsesClass(PhpDocConstantResolver::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[CoversClass(PhpDocItemCompiler::class)]
#[UsesClass(PhpDocLiteral::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLexer::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSyntaxError::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocCallableParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenState::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeTree::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNestedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocAtomicTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNamedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocHtmlDetector::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocGenericParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantNameParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocCallableReturnParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocArrayShapeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocShapeItemParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagValues::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocOtherTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocMethodTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocMethodParameterTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTextParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDescriptionParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineArrayParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTag::class)]
#[UsesClass(PhpDocUnionCompiler::class)]
#[UsesClass(TargetClass::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(TypeCompiler::class)]
#[CoversClass(UnionBuilder::class)]
#[UsesClass(UnionNode::class)]
final class LiteralTypeTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    public function testLiteralAndCollectionDeclarationsStayDistinct(): void
    {
        $object = new class(1) {
            /** @var list<int> */
            public array $items = [];
            /** @var positive-int */
            public int $refined = 1;
            /** @var list<int>|positive-int */
            public array|int $union = 1;

            public function __construct(
                public int $_plain,
            ) {}
        };
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($object, 'items')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($object, 'union')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($object, 'refined')));
        static::assertInstanceOf(ListNode::class, self::compile(new ReflectionProperty($object, 'items')));
        static::assertInstanceOf(
            ScalarNode::class,
            self::compile(new ReflectionParameter([$object::class, '__construct'], '_plain')),
        );

        $invalid = new class(1) {
            public const float NOT_FINITE = INF;

            /** @param int|float|self::NOT_FINITE $value */
            public function __construct(
                public int|float $value,
            ) {}
        };
        static::assertInstanceOf(
            DecodeError::class,
            self::compile(new ReflectionParameter([$invalid::class, '__construct'], 'value')),
        );
    }

    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testOrdinaryPhpDocDoesNotConstrainScalarValues(): void
    {
        $object = new class {
            /** @var int */
            public int $integer = 42;
            /** @var string|null */
            public string|null $text = null;
            /** @var null|bool */
            public bool|null $flag = null;
            /** @var null */
            public null $nothing = null;
        };
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($object, 'integer')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($object, 'text')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($object, 'flag')));
        static::assertSame('null', (string) self::compile(new ReflectionProperty($object, 'nothing')));
    }

    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws \JsonException
     */
    public function testLiteralFieldDeclarations(): void
    {
        $object = new class(11, 12) {
            public int $value;
            public int $second;
            /** @var 13 */
            public int $property;

            /**
             * @param 11 $value
             * @param 12 $second
             */
            public function __construct(int $value, int $second)
            {
                $this->value = $value;
                $this->second = $second;
                $this->property = 13;
            }

            /** @param 12 $value */
            public function other(int $value): void
            {
                $this->value = $value;
            }
        };
        static::assertSame(
            '11',
            (string) self::compile(new ReflectionParameter([$object::class, '__construct'], 'value')),
        );
        static::assertSame(
            '12',
            (string) self::compile(new ReflectionParameter([$object::class, '__construct'], 'second')),
        );
        static::assertSame('12', (string) self::compile(new ReflectionParameter([$object::class, 'other'], 'value')));
        static::assertSame('13', (string) self::compile(new ReflectionProperty($object, 'property')));

        $ordinary = new class {
            /** @var DateTimeImmutable */
            public DateTimeImmutable $object;
            public int $undocumented;
            /** @var positive-int */
            public int $refined;
            /** @var list<string> */
            public array $nonLiteral;

            public function __construct()
            {
                $this->object = new DateTimeImmutable();
                $this->undocumented = 0;
                $this->refined = 1;
                $this->nonLiteral = [];
            }
        };
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($ordinary, 'object')));
        static::assertNotInstanceOf(
            LiteralNode::class,
            self::compile(new ReflectionProperty($ordinary, 'undocumented')),
        );
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($ordinary, 'refined')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($ordinary, 'nonLiteral')));

        $ordinaryWithMetadata = new class {
            /** @var int @since 1 */
            public int $value = 1;
        };
        static::assertNotInstanceOf(
            LiteralNode::class,
            self::compile(new ReflectionProperty($ordinaryWithMetadata, 'value')),
        );

        $tagged = new class {
            /**
             * @since 1
             * @var 'a@b'
             */
            public string $value = 'a@b';
        };
        static::assertSame("'a@b'", PhpDocFieldType::resolve(new ReflectionProperty($tagged, 'value'))?->name);

        $enum = new class {
            /** @var StringBackedStatus::Ready */
            public StringBackedStatus $status;

            public function __construct()
            {
                $this->status = StringBackedStatus::Ready;
            }
        };
        $whole = LiteralNode::of('3.0');
        static::assertSame(['float', 'int'], $whole->valueTypes());
        static::assertSame(3.0, $whole->decode(3.0, 'value'));
        static::assertSame(3.0, $whole->decode(3, 'value'));
        static::assertSame(['string'], LiteralNode::of("'3'")->valueTypes());

        $status = self::compile(new ReflectionProperty($enum, 'status'));
        static::assertInstanceOf(LiteralNode::class, $status);
        static::assertSame(StringBackedStatus::Ready, $status->decode('ready', 'status'));
    }

    /** @throws \PHPUnit\Framework\Exception|ReflectionException */
    public function testLiteralMarkers(): void
    {
        $properties = new class {
            /** @var 'ready' */
            public string $literal = 'ready';

            /** @var positive-int */
            public int $refined = 1;

            /** @var stdClass */
            public stdClass $object;

            public int $undocumented = 1;

            /** @var null */
            public string|null $nothing = null;

            public function __construct()
            {
                $this->object = new stdClass();
            }
        };

        static::assertInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($properties, 'literal')));
        static::assertInstanceOf(NullOnlyNode::class, self::compile(new ReflectionProperty($properties, 'nothing')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($properties, 'refined')));
        static::assertNotInstanceOf(LiteralNode::class, self::compile(new ReflectionProperty($properties, 'object')));
        static::assertNotInstanceOf(
            LiteralNode::class,
            self::compile(new ReflectionProperty($properties, 'undocumented')),
        );

        static::assertSame(
            ['const:BAR' => 'Foo\\BAR', 'const:Qux' => 'Foo\\BAZ'],
            PhpDocImportStatement::parse('const Foo\\{BAR, BAZ as Qux}'),
        );
        static::assertSame(
            ['bar' => 'Foo\\Bar', 'const:BAZ' => 'Foo\\BAZ', 'const:Q' => 'Foo\\QUX'],
            PhpDocImportStatement::parse('Foo\\{Bar, const BAZ, const QUX as Q}'),
        );
        static::assertSame([], PhpDocImportStatement::parse('function Foo\\bar'));
        static::assertSame([], PhpDocImportStatement::parse('function Foo\\{bar, baz}'));
        static::assertSame(['const:BAR' => 'Foo\\BAR'], PhpDocImportStatement::parse('const \\Foo\\BAR'));
        static::assertSame(
            ['const:BAR' => 'Foo\\BAR', 'const:BAZ' => 'Foo\\BAZ'],
            PhpDocImportStatement::parse('const Foo\\ {BAR, BAZ}'),
        );
        static::assertSame(
            ['const:BAR' => 'Foo\\BAR', 'const:BAZ' => 'Foo\\BAZ'],
            PhpDocImportStatement::parse('CONST Foo\\{BAR, BAZ}'),
        );
        $subject = new class('ready', 42, StringBackedStatus::Ready, true, null) {
            /**
             * @param 'ready' $_string
             * @param 42 $_integer
             * @param StringBackedStatus::Ready $_constant
             * @param true $_boolean
             * @param null $_nullable
             */
            public function __construct(
                public string $_string,
                public int $_integer,
                public StringBackedStatus $_constant,
                public bool $_boolean,
                public null $_nullable,
            ) {}
        };
        foreach ([
            '_string' => "'ready'",
            '_integer' => '42',
            '_constant' => StringBackedStatus::class . '::Ready',
            '_boolean' => 'true',
            '_nullable' => 'null',
        ] as $name => $expected) {
            $parameter = new ReflectionParameter([$subject::class, '__construct'], $name);
            static::assertSame($expected, (string) self::compile($parameter), $name);
        }
    }

    public function testIntegerBoundariesAndNotation(): void
    {
        foreach ([
            ['0', 0],
            ['-0', 0],
            ['+42', 42],
            ['-42', -42],
            ['42', 42],
            ['1E2', 100.0],
            ['+1.5e-1', 0.15],
            [(string) PHP_INT_MIN, PHP_INT_MIN],
            [(string) PHP_INT_MAX, PHP_INT_MAX],
            ['9223372036854775808', null],
            ['-9223372036854775809', null],
            ['92233720368547758070', null],
            ['-92233720368547758080', null],
            ['0X2A', null],
            ['0B101010', null],
            ['0O52', null],
            ['052', null],
            ['00', null],
            ['4_2', null],
            ['foo', null],
            ['1e999', null],
        ] as [$source, $expected]) {
            static::assertSame($expected, PhpDocLiteralNumber::parse($source), $source);
        }
    }

    public function testUnicodeScalarBoundaries(): void
    {
        $literal = static function (string $source): string|int|float|bool|BackedEnum|null {
            $type = PhpDocTypeParser::parse($source);
            return $type === null ? null : PhpDocLiteral::value($type->name);
        };
        foreach ([
            ['0',                        "\u{0}"],
            ['d7ff',                     "\u{d7ff}"],
            ['e000',                     "\u{e000}"],
            ['ffff',                     "\u{ffff}"],
            ['10000',                    "\u{10000}"],
            ['103ff',                    "\u{103ff}"],
            ['10400',                    "\u{10400}"],
            ['10ffff',                   "\u{10ffff}"],
            ['d800',                     "\xED\xA0\x80"],
            ['dfff',                     "\xED\xBF\xBF"],
            ['110000',                   "\xF4\x90\x80\x80"],
            ['ffffffff',                 "\u{fffd}"],
            ['ffffffffffffffffffffffff', "\0"],
        ] as [$code, $expected]) {
            static::assertSame($expected, $literal('"\\u{' . $code . '}"'), $code);
        }
        static::assertNull($literal('not quoted'));
        static::assertNull($literal("'mismatched\""));
    }

    /** @throws ReflectionException */
    public function testConstantValuesKeepScalarTypesAndEscapedStrings(): void
    {
        static::assertSame("'a\\\\b\\'c'", PhpDocConstantValues::name("a\\b'c"));
        static::assertSame("'a\0b'", PhpDocConstantValues::name("a\0b"));
        static::assertSame('null', PhpDocConstantValues::name(null));
        static::assertSame('true', PhpDocConstantValues::name(true));
        static::assertSame('false', PhpDocConstantValues::name(false));
        static::assertSame(
            StringBackedStatus::class . '::Ready',
            PhpDocConstantValues::name(StringBackedStatus::Ready),
        );
        static::assertNull(PhpDocConstantValues::name(INF));
        static::assertNull(PhpDocConstantValues::name(NAN));
        static::assertNull(PhpDocConstantValues::name([]));
        $object = new class {
            public const array VALUES = [42];
        };
        static::assertNull(PhpDocConstantValues::resolve($object::class, 'VALUES'));
    }

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    public function testNativeBooleanLiteralsRejectTheirOppositeValues(): void
    {
        foreach (['yes' => true, 'no' => false] as $field => $expected) {
            $type = $expected ? 'true' : 'false';
            static::assertTrue(ValueTypeMatcher::matches($expected, $type, false), $field);
            static::assertFalse(ValueTypeMatcher::matches(!$expected, $type, false), $field);
        }
    }

    /** @throws ReflectionException */
    private static function compile(ReflectionParameter|ReflectionProperty $field): Node|DecodeError
    {
        $type = $field->getType();
        $class = $field->getDeclaringClass();
        static::assertNotNull($type);
        static::assertNotNull($class);
        return new TypeCompiler()->field($field, $type);
    }
}
