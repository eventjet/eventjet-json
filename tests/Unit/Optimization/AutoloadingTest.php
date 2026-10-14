<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\CollectionCompiler;
use Eventjet\Json\Internal\CollectionUnionCompiler;
use Eventjet\Json\Internal\CollectionUnionNatives;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldNameRules;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\LiteralFieldCompiler;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NativeUnionCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarItems;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
use Eventjet\Json\Internal\PhpDocCallableParser;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocCollectionCompiler;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocHtmlDetector;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocItemCompiler;
use Eventjet\Json\Internal\PhpDocLexer;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocSimpleTags;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocUnionCompiler;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Unit\Fixtures\DeferredListItem;
use Eventjet\Json\Test\Unit\Fixtures\DeferredValueEnum;
use Eventjet\Json\Test\Unit\Fixtures\JsonObject;
use Eventjet\Json\Test\Unit\Fixtures\PropertyCountingReflection;
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
#[CoversClass(ConstructorPlan::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(ConstructorParameter::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(PublicProperties::class)]
#[UsesClass(Field::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(TargetClass::class)]
#[CoversClass(FieldNameRules::class)]
#[UsesClass(CollectionCompiler::class)]
#[UsesClass(CollectionUnionCompiler::class)]
#[UsesClass(CollectionUnionNatives::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(NativeUnionCompiler::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
#[UsesClass(PhpDocCollectionCompiler::class)]
#[CoversClass(PhpDocFieldType::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocItemCompiler::class)]
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
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagSelector::class)]
#[UsesClass(PhpDocUnionCompiler::class)]
#[UsesClass(PublicPropertyTypeValidator::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[UsesClass(LiteralFieldCompiler::class)]
#[UsesClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[CoversClass(UnionNode::class)]
#[UsesClass(ClassDecoder::class)]
#[UsesClass(ClassDecoders::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(InvalidValue::class)]
#[UsesClass(MappedObjectSerializer::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\UnsupportedCollection::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\LiteralNode::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantValues::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteral::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralNumber::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNumbers::class)]
#[UsesClass(\Eventjet\Json\Json::class)]
final class AutoloadingTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws TypeError
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
                static::assertNotNull(new ConstructorParameter($parameter, $class)->nativeCheck());
                $compiler = new TypeCompiler();
                $property = $class->getProperty($name);
                foreach ([$parameter, $property] as $field) {
                    $node = $compiler->field($field, $type);
                    static::assertNotInstanceOf(DecodeError::class, $node);
                    static::assertSame($value, $node->decode($value, $name));
                }
            }
        } finally {
            spl_autoload_unregister($autoload);
        }

        foreach (['int', 'float', 'string', 'bool'] as $builtin) {
            static::assertNotContains($builtin, $requests->names);
        }
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     * @throws JsonException
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
            static::assertInstanceOf(DecodeError::class, $enum->decode(1, 'value'));
            static::assertNotContains(DeferredEnumBacking::class, $requests->names);
            static::assertSame(DeferredValueEnum::Ready, $enum->decode('ready', 'value'));
            static::assertContains(DeferredEnumBacking::class, $requests->names);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /**
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFieldNameLookupsReuseMetadataIncludingUnannotatedClasses(): void
    {
        foreach ([
            [MappedReference::class, ['ref' => '$ref']],
            [EmptyObject::class, []],
        ] as [$name, $expected]) {
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
