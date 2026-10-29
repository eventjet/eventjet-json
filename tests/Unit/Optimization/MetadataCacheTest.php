<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassField;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\CollectionDeclarationError;
use Eventjet\Json\Internal\Error\DecodeErrors;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\Internal\FieldNameRules;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NamedTypeCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarItems;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
use Eventjet\Json\Internal\NodeCompiler;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocConstantResolver;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocIntegerRange;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocNarrowing;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeResolver;
use Eventjet\Json\Internal\RootDescriptor;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\UnionConflicts;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\PublicPropertiesWithConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\JsonObject;
use Eventjet\Json\Test\Unit\Fixtures\ThrownDecodeError;
use JsonException;
use JsonSerializable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

use function class_alias;

#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(DecodeErrors::class)]
#[CoversClass(PhpDocFieldType::class)]
#[UsesClass(CollectionDeclarationError::class)]
#[UsesClass(PhpDocType::class)]
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
#[UsesClass(\Eventjet\Json\Internal\PhpDocChildKind::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteral::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTag::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(TargetClass::class)]
#[UsesClass(FieldNameRules::class)]
#[CoversClass(NamedTypeCompiler::class)]
#[CoversClass(NodeCompiler::class)]
#[UsesClass(PhpDocNarrowing::class)]
#[UsesClass(PhpDocTypeResolver::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(LiteralNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
#[UsesClass(PhpDocClassNameResolver::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(PhpDocTupleEntry::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[UsesClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[UsesClass(UnionConflicts::class)]
#[UsesClass(UnionNode::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassField::class)]
#[CoversClass(ClassDecoders::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(InvalidValue::class)]
#[UsesClass(UnsupportedField::class)]
#[UsesClass(UnsupportedFieldClass::class)]
#[UsesClass(UnsupportedTarget::class)]
#[UsesClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(RootDescriptor::class)]
#[UsesClass(Json::class)]
#[UsesClass(JsonType::class)]
#[UsesClass(PhpDocConstantResolver::class)]
#[UsesClass(PhpDocIntegerRange::class)]
final class MetadataCacheTest extends TestCase
{
    /**
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws DecodeError
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testClassMetadataIsReusedIncludingEmptyClasses(): void
    {
        foreach ([PublicPropertiesWithConstructor::class, ScalarFields::class] as $name) {
            $decoder = ClassDecoders::get($name);
            static::assertSame($decoder, ClassDecoders::get($name));
        }
        $failed = /** @throws DecodeError|ReflectionException */ static fn(): object => ClassDecoders::get(
            StaticConstructorParameterProperty::class,
        );
        static::assertNotSame(ThrownDecodeError::of($failed), ThrownDecodeError::of($failed));

        $first = new class(1) {
            public function __construct(
                public int $value,
            ) {}
        };
        $second = new class('second') {
            public function __construct(
                public string $value,
            ) {}
        };
        $firstClass = new ReflectionClass($first);
        static::assertEquals($first, ClassDecoders::hydrate($first::class, JsonObject::of(['value' => 1])));
        static::assertEquals($second, ClassDecoders::hydrate($second::class, JsonObject::of(['value' => 'second'])));
        $firstDecoder = ClassDecoders::get($first::class);
        static::assertNotSame($firstDecoder, ClassDecoders::get($second::class));
        static::assertEquals(
            $firstClass->newInstance(2),
            ClassDecoders::hydrate($first::class, JsonObject::of(['value' => 2])),
        );
        static::assertSame($firstDecoder, ClassDecoders::get($first::class));

        $booleans = new class(false, true) {
            public function __construct(
                public false $no,
                public true $yes,
            ) {}
        };
        static::assertEquals($booleans, ClassDecoders::hydrate($booleans::class, JsonObject::of([
            'no' => false,
            'yes' => true,
        ])));
        static::assertEquals(
            InvalidValue::typeMismatch('no', 'false', true)->whileCreating($booleans::class),
            ThrownDecodeError::of(
                /** @throws DecodeError|JsonException */ static fn(): object => ClassDecoders::hydrate($booleans::class, JsonObject::of([
                    'no' => true,
                    'yes' => true,
                ])),
            ),
        );
        static::assertEquals(
            InvalidValue::typeMismatch('yes', 'true', false)->whileCreating($booleans::class),
            ThrownDecodeError::of(
                /** @throws DecodeError|JsonException */ static fn(): object => ClassDecoders::hydrate($booleans::class, JsonObject::of([
                    'no' => false,
                    'yes' => false,
                ])),
            ),
        );
        $numeric = new class(1) implements JsonSerializable {
            public function __construct(
                #[Field('1')]
                public int $value,
            ) {}

            /** @return array{1: int} */
            #[Override]
            public function jsonSerialize(): array
            {
                return [1 => $this->value];
            }
        };
        static::assertEquals($numeric, ClassDecoders::hydrate($numeric::class, JsonObject::of(['1' => 1])));
        static::assertEquals(
            InvalidValue::typeMismatch('1', 'int', 'wrong')->whileCreating($numeric::class),
            ThrownDecodeError::of(
                /** @throws DecodeError|JsonException */ static fn(): object => ClassDecoders::hydrate($numeric::class, JsonObject::of([
                    '1' => 'wrong',
                ])),
            ),
        );

        $union = ClassDecoders::get(DistinctEnumScalarUnionField::class);
        static::assertEquals(
            new DistinctEnumScalarUnionField(IntBackedStatus::Ready),
            ClassDecoders::hydrate(DistinctEnumScalarUnionField::class, JsonObject::of(['value' => 1])),
        );
        static::assertEquals(
            new DistinctEnumScalarUnionField(IntBackedStatus::Pending),
            ClassDecoders::hydrate(DistinctEnumScalarUnionField::class, JsonObject::of(['value' => 0])),
        );
        static::assertSame($union, ClassDecoders::get(DistinctEnumScalarUnionField::class));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws UnknownClassOrInterfaceException
     * @throws DecodeError
     */
    public function testRootDescriptorNodesAreReusedAcrossEquivalentDescriptors(): void
    {
        $node = new RootDescriptor(['array'], ScalarFields::class)->node();
        static::assertInstanceOf(ListNode::class, $node);
        static::assertSame($node, new RootDescriptor(['array'], ScalarFields::class)->node());
        foreach ([
            [['object'], ScalarFields::class],
            [['array', 'array'], ScalarFields::class],
            [['array'], PublicPropertiesWithConstructor::class],
        ] as [$shapes, $class]) {
            static::assertNotSame($node, new RootDescriptor($shapes, $class)->node());
        }
    }

    /**
     * @param class-string $dependency
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     */
    #[TestWith(['MetadataCacheDeferredRootItem'])]
    public function testRootDescriptorsAreCompiledAgainAfterTheItemClassLoads(string $dependency): void
    {
        static::assertInstanceOf(DecodeError::class, Json::decode('[]', JsonType::array($dependency)));
        static::assertTrue(class_alias(ScalarFields::class, $dependency));
        static::assertSame([], Json::decode('[]', JsonType::array($dependency)));
    }

    /**
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws DecodeError
     */
    public function testEnumConversionsPreserveBackingTypesAndRejectUnknownValues(): void
    {
        static::assertNull(EnumNode::of(NonBackedStatus::class));
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            foreach ([
                [IntBackedStatus::class, 1, IntBackedStatus::Ready],
                [IntBackedStatus::class, '1', null],
                [IntBackedStatus::class, 1.0, null],
                [IntBackedStatus::class, true, null],
                [IntBackedStatus::class, null, null],
                [IntBackedStatus::class, 2, null],
                [StringBackedStatus::class, StringBackedStatus::Ready->value, StringBackedStatus::Ready],
                [StringBackedStatus::class, StringBackedStatus::Pending->value, StringBackedStatus::Pending],
                [StringBackedStatus::class, 'unknown', null],
            ] as [$enum, $value, $expected]) {
                $node = EnumNode::of($enum);
                static::assertNotNull($node);
                if ($expected === null) {
                    static::assertSame(
                        DecodeErrorKind::InvalidValue,
                        ThrownDecodeError::of(
                            /** @throws DecodeError|JsonException */ static fn(): mixed => $node->decode(
                                $value,
                                'value',
                            ),
                        )->kind,
                    );
                    continue;
                }
                static::assertSame($expected, $node->decode($value, 'value'));
            }
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    #[TestWith(['Named', ''])]
    #[TestWith(['Union', '|int'])]
    public function testUnresolvedFieldTypesAreRetriedAfterDependencyLoads(string $name, string $suffix): void
    {
        $dependency = 'PublicMetadataDeferredClass' . $name;
        $enumDependency = 'MetadataCacheDeferredEnum' . $name;
        $class = CollectionDeclarationFixture::create($enumDependency, '', 'var');

        [$node, $unresolved] = self::compileValue($class);
        static::assertInstanceOf(UncheckedNode::class, $node);
        static::assertSame([$enumDependency], $unresolved);
        $nullable = CollectionDeclarationFixture::create('?' . $enumDependency, '', 'var');
        static::assertInstanceOf(UncheckedNode::class, self::compileValue($nullable)[0]);
        static::assertTrue(class_alias(NonBackedStatus::class, $enumDependency));
        [$node, $unresolved] = self::compileValue($class);
        static::assertInstanceOf(DecodeError::class, $node);
        static::assertSame([], $unresolved);

        $name = CollectionDeclarationFixture::create($dependency . $suffix, '', 'var');
        $incomplete = ClassDecoders::get($name);
        static::assertFalse($incomplete->complete);
        static::assertNotSame($incomplete, ClassDecoders::get($name));
        static::assertTrue(class_alias(ScalarFields::class, $dependency));
        $decoder = ClassDecoders::get($name);
        static::assertSame($decoder, ClassDecoders::get($name));
    }

    /**
     * A constructor's doc comment declares all parameters, so it is read once and not once for each parameter. This
     * holds for doc comments that need the full PHPDoc parser, too.
     *
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function testDocCommentsAreParsedOnce(): void
    {
        $object = new class(1, 2) {
            /** @var 3 */
            public int $third = 3;

            /**
             * @param 1 $first
             * @param 2 $second
             */
            public function __construct(
                public int $first,
                public int $second,
            ) {}
        };
        foreach ([
            new ReflectionParameter([$object::class, '__construct'], 'first'),
            new ReflectionParameter([$object::class, '__construct'], 'second'),
            new ReflectionProperty($object, 'third'),
            self::docParameter("/**\n * @param list<int> \$value\n */"),
            self::docParameter("/**\n * @return void\n * @param list<int> \$value\n */"),
        ] as $field) {
            $type = PhpDocFieldType::resolve($field);
            static::assertNotNull($type);
            static::assertSame($type, PhpDocFieldType::resolve($field));
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws JsonException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testResolvedNonCollectionDeclarationsAreCacheable(): void
    {
        foreach (['int', 'int|string', '\\' . NonBackedStatus::class . '|int'] as $declaration) {
            [$node, $unresolved] = self::compileValue(CollectionDeclarationFixture::create($declaration, '', 'var'));
            static::assertNotInstanceOf(DecodeError::class, $node);
            static::assertSame([], $unresolved);
        }

        $collectionClass = new ReflectionClass(CollectionDeclarationFixture::create('array', 'list<int>', 'param'));
        $collections = ClassDecoders::get($collectionClass->getName());
        static::assertSame($collections, ClassDecoders::get($collectionClass->getName()));

        $first = new class {
            /** @var list<int> */
            public array $value = [];
        };
        $second = new class {
            /** @var list<string> */
            public array $value = [];
        };
        foreach ([
            [$first::class,  'list<int>'],
            [$second::class, 'list<string>'],
        ] as [$class, $expected]) {
            $node = self::compileValue($class)[0];
            static::assertInstanceOf(Node::class, $node);
            static::assertSame($expected, (string) $node);
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws JsonException
     */
    public function testUnresolvedUnionMetadataIsRetriedAfterDependencyLoads(): void
    {
        $dependency = 'MetadataCacheDeferredUnionClass';
        $class = CollectionDeclarationFixture::create($dependency . '|int', '', 'var');

        [$node, $unresolved] = self::compileValue($class);
        static::assertNotInstanceOf(DecodeError::class, $node);
        static::assertSame([$dependency], $unresolved);
        static::assertTrue(class_alias(ParentClassFieldBase::class, $dependency));
        static::assertInstanceOf(DecodeError::class, self::compileValue($class)[0]);

        $enumDependency = 'EnumConversionDeferredUnion';
        $enumClass = CollectionDeclarationFixture::create($enumDependency . '|int', '', 'var');
        $node = self::compileValue($enumClass)[0];
        static::assertNotInstanceOf(DecodeError::class, $node);
        static::assertSame(
            DecodeErrorKind::InvalidValue,
            ThrownDecodeError::of(
                /** @throws DecodeError|JsonException */ static fn(): mixed => $node->decode(
                    StringBackedStatus::Ready->value,
                    'value',
                ),
            )->kind,
        );
        static::assertTrue(class_alias(StringBackedStatus::class, $enumDependency));
        $node = self::compileValue($enumClass)[0];
        static::assertNotInstanceOf(DecodeError::class, $node);
        static::assertSame(StringBackedStatus::Ready, $node->decode(StringBackedStatus::Ready->value, 'value'));
    }

    /**
     * Compiles the `value` property of a class and returns the node with its dependencies, or the error with none.
     *
     * @param class-string $class
     * @return array{Node|DecodeError, list<string>}
     * @throws ReflectionException
     */
    private static function compileValue(string $class): array
    {
        $property = new ReflectionProperty($class, 'value');
        $type = $property->getType();
        static::assertNotNull($type);
        $node = ThrownDecodeError::orResult(
            /** @throws DecodeError|ReflectionException */ static fn(): Node => new TypeCompiler()->field(
                $property,
                $type,
            ),
        );
        return [$node, $node instanceof DecodeError ? [] : $node->dependencies()];
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function docParameter(string $doc): ReflectionParameter
    {
        $class = CollectionDeclarationFixture::create('array', $doc, 'param');
        return new ReflectionParameter([$class, '__construct'], 'value');
    }
}
