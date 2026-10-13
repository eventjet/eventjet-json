<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\DecodeErrorKind;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NamedTypeCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
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
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocNarrowing;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeResolver;
use Eventjet\Json\Internal\PhpDocTypeTokens;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\PublicPropertyValues;
use Eventjet\Json\Internal\RootDescriptor;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\ValueTypeMatcher;
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
use ReflectionProperty;
use RuntimeException;

use function class_alias;

#[CoversClass(ConstructorParameter::class)]
#[UsesClass(ConstructorPlan::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(PublicPropertyValues::class)]
#[UsesClass(PublicPropertyTypeValidator::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(PhpDocFieldType::class)]
#[UsesClass(PhpDocType::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(PhpDocTypeTokens::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[UsesClass(FieldNameCollisions::class)]
#[UsesClass(FieldNames::class)]
#[UsesClass(NamedTypeCompiler::class)]
#[CoversClass(NodeCompiler::class)]
#[UsesClass(PhpDocNarrowing::class)]
#[UsesClass(PhpDocTypeResolver::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
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
#[UsesClass(UnionNode::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassDecoders::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(RootDescriptor::class)]
#[UsesClass(Json::class)]
#[UsesClass(JsonType::class)]
final class MetadataCacheTest extends TestCase
{
    /**
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testClassMetadataIsReusedIncludingEmptyClasses(): void
    {
        foreach ([PublicPropertiesWithConstructor::class, ScalarFields::class] as $name) {
            $decoder = ClassDecoders::get($name);
            static::assertInstanceOf(ClassDecoder::class, $decoder);
            static::assertSame($decoder, ClassDecoders::get($name));
        }
        static::assertInstanceOf(DecodeError::class, ClassDecoders::get(StaticConstructorParameterProperty::class));
        static::assertNotSame(
            ClassDecoders::get(StaticConstructorParameterProperty::class),
            ClassDecoders::get(StaticConstructorParameterProperty::class),
        );

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
            DecodeError::fieldTypeMismatch($booleans::class, 'no', 'false', true),
            ClassDecoders::hydrate($booleans::class, JsonObject::of(['no' => true, 'yes' => true])),
        );
        static::assertEquals(
            DecodeError::fieldTypeMismatch($booleans::class, 'yes', 'true', false),
            ClassDecoders::hydrate($booleans::class, JsonObject::of(['no' => false, 'yes' => false])),
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
            DecodeError::fieldTypeMismatch($numeric::class, '1', 'int', 'wrong'),
            ClassDecoders::hydrate($numeric::class, JsonObject::of(['1' => 'wrong'])),
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
     */
    public function testEnumConversionsPreserveBackingTypesAndRejectUnknownValues(): void
    {
        static::assertNull(EnumNode::of(ScalarFields::class, NonBackedStatus::class));
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
                $node = EnumNode::of(ScalarFields::class, $enum);
                static::assertNotNull($node);
                $converted = $node->decode($value, 'value');
                if ($expected === null) {
                    static::assertInstanceOf(DecodeError::class, $converted);
                    continue;
                }
                static::assertSame($expected, $converted);
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
        static::assertInstanceOf(DecodeError::class, ClassDecoders::get($name));
        static::assertTrue(class_alias(ScalarFields::class, $dependency));
        $decoder = ClassDecoders::get($name);
        static::assertInstanceOf(ClassDecoder::class, $decoder);
        static::assertSame($decoder, ClassDecoders::get($name));
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
        static::assertInstanceOf(ClassDecoder::class, $collections);
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
        static::assertInstanceOf(DecodeError::class, $node->decode(StringBackedStatus::Ready->value, 'value'));
        static::assertTrue(class_alias(StringBackedStatus::class, $enumDependency));
        $node = self::compileValue($enumClass)[0];
        static::assertNotInstanceOf(DecodeError::class, $node);
        static::assertSame(StringBackedStatus::Ready, $node->decode(StringBackedStatus::Ready->value, 'value'));
    }

    /**
     * Compiles the `value` property of a class and returns the node with its dependencies.
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
        $node = new TypeCompiler($class)->field($property, $type);
        return [$node, $node instanceof DecodeError ? [] : $node->dependencies()];
    }
}
