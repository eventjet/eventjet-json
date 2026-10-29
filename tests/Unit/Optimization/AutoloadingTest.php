<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassField;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\CollectionDeclarationError;
use Eventjet\Json\Internal\Error\DecodeFailure;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\FieldNameRules;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NamedTypeCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarItems;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
use Eventjet\Json\Internal\NodeCompiler;
use Eventjet\Json\Internal\PhpDocCallableParser;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocConstantImportStatement;
use Eventjet\Json\Internal\PhpDocConstantResolver;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocHtmlDetector;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocIntegerRange;
use Eventjet\Json\Internal\PhpDocLexer;
use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocLiteralNativeType;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocNarrowing;
use Eventjet\Json\Internal\PhpDocSimpleTags;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeResolver;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\Address;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedObjectFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Unit\Fixtures\DeferredListItem;
use Eventjet\Json\Test\Unit\Fixtures\DeferredValueEnum;
use Eventjet\Json\Test\Unit\Fixtures\JsonObject;
use Eventjet\Json\Test\Unit\Fixtures\PropertyCountingReflection;
use Eventjet\Json\Test\Unit\Fixtures\ThrownDecodeFailure;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use TypeError;

use function class_exists;
use function enum_exists;
use function json_encode;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

#[CoversClass(PhpDocClassNameResolver::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(DecodeFailure::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(Field::class)]
#[CoversClass(TargetClass::class)]
#[CoversClass(FieldNameRules::class)]
#[UsesClass(NamedTypeCompiler::class)]
#[UsesClass(NodeCompiler::class)]
#[CoversClass(PhpDocNarrowing::class)]
#[CoversClass(PhpDocTypeResolver::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
#[CoversClass(PhpDocFieldType::class)]
#[UsesClass(CollectionDeclarationError::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(PhpDocTupleEntry::class)]
#[UsesClass(PhpDocType::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLexer::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSyntaxError::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocCallableParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenState::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeTree::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNestedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocAtomicTypeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocNamedTypeParser::class)]
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
#[UsesClass(\Eventjet\Json\Internal\PhpDocChildKind::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTag::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[CoversClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[UsesClass(LiteralNode::class)]
#[UsesClass(PhpDocConstantImportStatement::class)]
#[UsesClass(PhpDocConstantResolver::class)]
#[UsesClass(PhpDocLiteral::class)]
#[CoversClass(UnionNode::class)]
#[UsesClass(ClassDecoder::class)]
#[UsesClass(ClassField::class)]
#[UsesClass(ClassDecoders::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(InvalidValue::class)]
#[UsesClass(UnsupportedField::class)]
#[UsesClass(MappedObjectSerializer::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\UnsupportedCollection::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantValues::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocIntegerRange::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Json::class)]
#[UsesClass(PhpDocLiteralNativeType::class)]
final class AutoloadingTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws TypeError
     * @throws DecodeFailure
     */
    public function testEmptyObjectListsDoNotAutoloadItemClasses(): void
    {
        static::assertFalse(class_exists(DeferredListItem::class, autoload: false));
        $requests = new class {
            public int $count = 0;
        };
        $autoload = static function (string $name) use ($requests): void {
            if ($name === DeferredListItem::class) {
                ++$requests->count;
            }
        };
        spl_autoload_register($autoload, prepend: true);
        try {
            $items = new ListNode(new ObjectNode(DeferredListItem::class, false), false);
            static::assertSame([], $items->decode([], 'items'));
            static::assertSame(0, $requests->count);
            static::assertFalse(class_exists(DeferredListItem::class, autoload: false));
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /** @throws ReflectionException */
    public function testGlobalNamespaceRelativeNamesHaveNoLeadingSeparator(): void
    {
        /** @var ReflectionClass<object> $class */
        $class = new ReflectionClass(stdClass::class);

        static::assertSame('stdClass', PhpDocClassNameResolver::resolve($class, 'namespace\stdClass'));
    }

    /**
     * @throws Exception
     * @throws TypeError
     * @throws UnknownClassOrInterfaceException
     * @throws JsonException
     * @throws ReflectionException
     * @throws DecodeFailure
     */
    public function testBuiltinFieldsNeverInvokeAutoloaders(): void
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
        /** @var array<string, bool|float|int|string|null> $values */
        $values = [
            'integer' => 1,
            'float' => 1.5,
            'string' => 'value',
            'boolean' => true,
            'nullable' => null,
        ];
        $class = new ReflectionClass($target);
        // A PHPDoc type that does not narrow an unsupported native type leaves the native type in charge.
        $imprecise = new ReflectionProperty(
            new class {
                /** @var stdClass */
                public object $value;

                public function __construct()
                {
                    $this->value = new stdClass();
                }
            },
            'value',
        );
        $requests = new class {
            /** @var list<string> */
            public array $names = [];
        };
        $autoload = static function (string $name) use ($requests): void {
            $requests->names[] = $name;
        };
        spl_autoload_register($autoload);

        try {
            static::assertEquals($target, ClassDecoders::hydrate($class->getName(), JsonObject::of($values)));
            foreach ($values as $name => $value) {
                $parameter = new ReflectionParameter([$class->getName(), '__construct'], $name);
                $type = $parameter->getType();
                static::assertInstanceOf(ReflectionNamedType::class, $type);
                $compiler = new TypeCompiler();
                $property = $class->getProperty($name);
                foreach ([$parameter, $property] as $field) {
                    static::assertSame($value, $compiler->field($field, $type)->decode($value, $name));
                }
            }
            $impreciseType = $imprecise->getType();
            static::assertNotNull($impreciseType);
            static::assertSame(
                DecodeErrorKind::UnsupportedDeclaration,
                ThrownDecodeFailure::of(
                    /**
                     * @throws DecodeFailure
                     * @throws ReflectionException
                     */
                    static fn(): object => new TypeCompiler()->field($imprecise, $impreciseType),
                )->kind,
            );
        } finally {
            spl_autoload_unregister($autoload);
        }

        foreach (['int', 'float', 'string', 'bool', 'object'] as $builtin) {
            static::assertNotContains($builtin, $requests->names);
        }
    }

    /**
     * Native class and enum fields, nullable or not, compile without the type tree and union that NodeCompiler would
     * build for them. Both paths compile the same nodes; this shortcut only makes the first decode in a request faster.
     *
     * @throws JsonException
     * @throws \PHPUnit\Framework\Exception
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNativeClassAndEnumFieldsSkipTheUnionCompiler(): void
    {
        $person = new Person('Ada', 'Lovelace');
        self::assertRoundTrips([
            new BackedEnumFields(StringBackedStatus::Ready, null, IntBackedStatus::Ready),
            new BackedEnumFields(StringBackedStatus::Ready, StringBackedStatus::Pending, IntBackedStatus::Ready),
            new NestedObjectFields($person, new Address('London', new Coordinates(51.5, -0.1)), null),
            new NestedObjectFields($person, new Address('London', new Coordinates(51.5, -0.1)), $person),
        ]);

        static::assertFalse(class_exists(NodeCompiler::class, autoload: false));
        static::assertFalse(class_exists(UnionBuilder::class, autoload: false));
        static::assertFalse(class_exists(NullOnlyNode::class, autoload: false));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     * @throws JsonException
     * @throws UnknownClassOrInterfaceException
     * @throws DecodeFailure
     */
    public function testLiteralFieldsNeverInvokeAutoloaders(): void
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
        $requests = new class {
            /** @var list<string> */
            public array $names = [];
        };
        $autoload = static function (string $name) use ($requests): void {
            $requests->names[] = $name;
        };
        spl_autoload_register($autoload);

        try {
            $constructor = [$target::class, '__construct'];
            $fields = [
                'string' => [new ReflectionParameter($constructor, 'string'), 'a'],
                'integer' => [new ReflectionParameter($constructor, 'integer'), 1],
                'boolean' => [new ReflectionParameter($constructor, 'boolean'), true],
                'constant' => [new ReflectionProperty($target, 'constant'), ExtendedLiteralFields::NUMBER],
                'booleans' => [new ReflectionProperty($target, 'booleans'), [true]],
                'floats' => [new ReflectionProperty($target, 'floats'), [1.5]],
                'integers' => [new ReflectionProperty($target, 'integers'), [1]],
                'strings' => [new ReflectionProperty($target, 'strings'), ['value']],
                'literals' => [new ReflectionProperty($target, 'literals'), [42]],
            ];
            foreach ($fields as $name => [$field, $value]) {
                $type = $field->getType();
                static::assertNotNull($type);
                static::assertSame($value, new TypeCompiler()
                    ->field($field, $type)
                    ->decode($value, $name));
            }
        } finally {
            spl_autoload_unregister($autoload);
        }

        static::assertSame([], $requests->names);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     * @throws JsonException
     * @throws DecodeFailure
     */
    public function testEnumBackingConstantsLoadOnlyForMatchingInputTypes(): void
    {
        static::assertTrue(enum_exists(DeferredValueEnum::class));
        static::assertFalse(class_exists(DeferredEnumBacking::class, autoload: false));
        $requests = new class {
            /** @var list<string> */
            public array $names = [];
        };
        $autoload = static function (string $name) use ($requests): void {
            $requests->names[] = $name;
        };
        spl_autoload_register($autoload, prepend: true);

        try {
            $enum = EnumNode::of(DeferredValueEnum::class);
            static::assertNotNull($enum);
            static::assertSame(
                DecodeErrorKind::InvalidValue,
                ThrownDecodeFailure::of(
                    /** @throws DecodeFailure|JsonException */ static fn(): object => $enum->decode(1, 'value'),
                )->kind,
            );
            static::assertNotContains(DeferredEnumBacking::class, $requests->names);
            static::assertSame(DeferredValueEnum::Ready, $enum->decode('ready', 'value'));
            static::assertContains(DeferredEnumBacking::class, $requests->names);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /** @throws DecodeFailure */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFieldNameLookupsReuseMetadataIncludingUnannotatedClasses(): void
    {
        foreach ([
            [MappedReference::class, ['ref' => '$ref']],
            [EmptyObject::class, []],
        ] as [$name, $expected]) {
            /** @mago-expect analysis:unhandled-thrown-type Reflecting an existing class cannot throw. */
            $class = new PropertyCountingReflection($name);
            static::assertSame($expected, TargetClass::fieldNames($class));
            static::assertSame($expected, TargetClass::fieldNames($class));
            static::assertSame(1, $class->propertyLookups);
            static::assertSame(1, $class->parentLookups);
        }
    }

    /**
     * Doc comments without tags are not read. Doc comments with only `@param` and `@var` tags whose types fit on one
     * line are read without the full PHPDoc parser. The full parser looks for HTML and callables only where they can
     * follow: an HTML end tag needs a slash after the opening, and a callable needs a later `(`.
     *
     * @throws ReflectionException
     * @throws JsonException
     * @throws RuntimeException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDocCommentsLoadOnlyTheParsersTheyNeed(): void
    {
        $untagged = new class([1]) {
            /** Takes 1 list. */
            public function __construct(
                /** @var list<int> */
                public array $items,
            ) {}
        };
        static::assertInstanceOf(DecodeError::class, Json::decode('{"items":[1]}', $untagged::class));
        static::assertFalse(class_exists(PhpDocSimpleTags::class, autoload: false));

        $number = '\\' . ExtendedLiteralFields::class . '::NUMBER';
        $status = '\\' . StringBackedStatus::class;
        $simple = [
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
                self::nestedList(62),
            ),
        ];
        self::assertRoundTrips($simple);
        static::assertFalse(class_exists(PhpDocLexer::class, autoload: false));
        static::assertFalse(class_exists(PhpDocIntegerRange::class, autoload: false));

        $full = [
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
        ];
        self::assertRoundTrips($full);
        static::assertTrue(class_exists(PhpDocLexer::class, autoload: false));
        static::assertFalse(class_exists(PhpDocHtmlDetector::class, autoload: false));
        static::assertFalse(class_exists(PhpDocCallableParser::class, autoload: false));
    }

    /**
     * Checks each object right after decoding it. When a mutant makes the parser loop, the time limit aborts the decode,
     * which returns the abort as a decode error. Checking it right away ends the test before the next decode loops
     * without a limit.
     *
     * @param list<object> $originals
     * @throws JsonException
     */
    private static function assertRoundTrips(array $originals): void
    {
        foreach ($originals as $original) {
            static::assertEquals($original, Json::decode(
                json_encode($original, JSON_THROW_ON_ERROR),
                $original::class,
            ));
        }
    }

    /** @return list<mixed> */
    private static function nestedList(int $depth): array
    {
        $list = [1];
        for ($level = 0; $level < $depth; ++$level) {
            $list = [$list];
        }
        return $list;
    }
}
