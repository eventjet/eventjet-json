<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\CollectionCompiler;
use Eventjet\Json\Internal\CollectionUnionCompiler;
use Eventjet\Json\Internal\ConstructorDecoder;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MappedConstructorPlan;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NativeUnionCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\EnumUnionNode;
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
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocCollectionCompiler;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocItemCompiler;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeTokens;
use Eventjet\Json\Internal\PhpDocUnionCompiler;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyHydrator;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\PublicPropertiesWithConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use stdClass;

use function array_keys;
use function class_alias;

#[CoversClass(MetadataCache::class)]
#[CoversClass(ConstructorParameter::class)]
#[UsesClass(ConstructorPlan::class)]
#[CoversClass(ConstructorDecoder::class)]
#[CoversClass(ClassJsonType::class)]
#[UsesClass(ObjectHydrator::class)]
#[UsesClass(PublicPropertyHydrator::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(PublicProperties::class)]
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
#[UsesClass(CollectionCompiler::class)]
#[UsesClass(CollectionUnionCompiler::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(MappedConstructorPlan::class)]
#[UsesClass(NativeUnionCompiler::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
#[UsesClass(PhpDocClassNameResolver::class)]
#[UsesClass(PhpDocCollectionCompiler::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocItemCompiler::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(PhpDocTupleEntry::class)]
#[UsesClass(PhpDocUnionCompiler::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[UsesClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[UsesClass(UnionNode::class)]
#[UsesClass(EnumUnionNode::class)]
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
        foreach ([
            [PublicPropertiesWithConstructor::class, ['label', 'active', 'inherited']],
            [ScalarFields::class, []],
            [StaticConstructorParameterProperty::class, []],
        ] as [$name, $expected]) {
            $class = new ReflectionClass($name);
            $properties = PublicProperties::resolve($class);
            static::assertIsArray($properties);
            static::assertSame($expected, array_keys($properties));
            static::assertSame($properties, PublicProperties::resolve($class));
        }

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
        $secondClass = new ReflectionClass($second);
        $planCache = new ReflectionProperty(ConstructorDecoder::class, 'plans');
        static::assertSame(['value' => 1], ConstructorDecoder::convert($firstClass, ['value' => 1], ''));
        static::assertSame(['value' => 'second'], ConstructorDecoder::convert($secondClass, ['value' => 'second'], ''));
        /** @var array<class-string, ConstructorPlan> $cachedPlans */
        $cachedPlans = $planCache->getValue();
        $firstPlan = $cachedPlans[$firstClass->getName()] ?? null;
        $secondPlan = $cachedPlans[$secondClass->getName()] ?? null;
        static::assertNotNull($firstPlan);
        static::assertNotNull($secondPlan);
        static::assertNotSame($firstPlan, $secondPlan);
        static::assertSame(['value' => 2], ConstructorDecoder::convert($firstClass, ['value' => 2], ''));
        static::assertSame($cachedPlans, $planCache->getValue());

        $converters = new ReflectionProperty(ConstructorPlan::class, 'converters');
        static::assertSame(['value' => null], $converters->getValue($firstPlan));

        $unionClass = new ReflectionClass(DistinctEnumScalarUnionField::class);
        static::assertSame(
            ['value' => IntBackedStatus::Ready],
            ConstructorDecoder::convert($unionClass, ['value' => 1], ''),
        );
        /** @var array<class-string, ConstructorPlan> $cachedPlans */
        $cachedPlans = $planCache->getValue();
        static::assertSame(
            ['value' => IntBackedStatus::Pending],
            ConstructorDecoder::convert($unionClass, ['value' => 0], ''),
        );
        static::assertSame($cachedPlans, $planCache->getValue());

        $descriptor = new ClassJsonType($firstClass->getName());
        $input = new stdClass();
        $input->value = 3;
        static::assertSame('object', $descriptor->rootShape());
        static::assertEquals($firstClass->newInstance(3), $descriptor->decode($input));
        static::assertInstanceOf(DecodeError::class, $descriptor->decode(false));
        static::assertSame(IntBackedStatus::Ready, new ClassJsonType(IntBackedStatus::class)->decode(1));
    }

    /**
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     */
    public function testEnumConversionsPreserveBackingTypesAndRejectUnknownValues(): void
    {
        static::assertNull(EnumNode::of(ScalarFields::class, NonBackedStatus::class, nullable: false));
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
                $node = EnumNode::of(ScalarFields::class, $enum, nullable: false);
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

    public function testRepeatedLookupsLoadOnceIncludingEmptyMetadata(): void
    {
        foreach ([[], false, new stdClass()] as $metadata) {
            /** @var MetadataCache<array<never, never>|stdClass|false> $cache */
            $cache = new MetadataCache();
            $counter = new class {
                public int $calls = 0;
            };
            $load = /** @return array<never, never>|stdClass|false */ static function () use (
                $counter,
                $metadata,
            ): array|stdClass|false {
                ++$counter->calls;
                return $metadata;
            };

            for ($lookup = 0; $lookup < 3; ++$lookup) {
                static::assertSame($metadata, $cache->resolve('field', $load));
            }
            static::assertSame(1, $counter->calls);
        }
    }

    public function testKeysAndCacheInstancesRemainIndependent(): void
    {
        /** @var MetadataCache<int> $first */
        $first = new MetadataCache();
        /** @var MetadataCache<int> $second */
        $second = new MetadataCache();
        $counter = new class {
            public int $calls = 0;
        };
        $load = static fn(): int => ++$counter->calls;

        static::assertSame(1, $first->resolve('first', $load));
        static::assertSame(2, $first->resolve('second', $load));
        static::assertSame(3, $second->resolve('first', $load));
        static::assertSame(1, $first->resolve('first', $load));
        static::assertSame(2, $first->resolve('second', $load));
        static::assertSame(3, $second->resolve('first', $load));
        static::assertSame(3, $counter->calls);
    }

    public function testUnresolvedMetadataAndErrorsAreRetried(): void
    {
        foreach ([null, DecodeError::invalidJson('invalid')] as $failure) {
            /** @var MetadataCache<stdClass|DecodeError|null> $cache */
            $cache = new MetadataCache();
            $counter = new class {
                public int $calls = 0;
            };
            $metadata = new stdClass();
            $load = static function () use ($counter, $failure, $metadata): stdClass|DecodeError|null {
                ++$counter->calls;
                return $counter->calls === 1 ? $failure : $metadata;
            };

            static::assertSame($failure, $cache->resolve('field', $load));
            static::assertSame($metadata, $cache->resolve('field', $load));
            static::assertSame($metadata, $cache->resolve('field', $load));
            static::assertSame(2, $counter->calls);
        }
    }

    public function testLoaderExceptionsAreRetried(): void
    {
        /** @var MetadataCache<array<never, never>> $cache */
        $cache = new MetadataCache();
        $failure = new RuntimeException('metadata unavailable');
        try {
            $cache->resolve('field', /** @throws RuntimeException */ static fn(): never => throw $failure);
            static::fail('The loader exception must propagate.');
        } catch (RuntimeException $caught) {
            static::assertSame($failure, $caught);
        }

        static::assertSame([], $cache->resolve('field', /** @return array<never, never> */ static fn(): array => []));
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
        static::assertTrue(class_alias(NonBackedStatus::class, $enumDependency));
        [$node, $unresolved] = self::compileValue($class);
        static::assertInstanceOf(DecodeError::class, $node);
        static::assertSame([], $unresolved);

        $name = CollectionDeclarationFixture::create($dependency . $suffix, '', 'var');
        $class = new ReflectionClass($name);

        static::assertInstanceOf(DecodeError::class, PublicProperties::resolve($class));
        static::assertTrue(class_alias(ScalarFields::class, $dependency));
        $properties = PublicProperties::resolve($class);
        static::assertIsArray($properties);
        static::assertSame(['value'], array_keys($properties));
        static::assertSame($properties, PublicProperties::resolve($class));
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
        $collections = ConstructorDecoder::convert($collectionClass, [], '');
        static::assertSame($collections, ConstructorDecoder::convert($collectionClass, [], ''));
        static::assertNotNull(ConstructorDecoder::cachedPlan($collectionClass->getName()));

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
     * Compiles the `value` property of a class.
     *
     * @param class-string $class
     * @return array{Node|DecodeError, list<string>}
     * @throws ReflectionException
     */
    private static function compileValue(string $class): array
    {
        $compiler = new TypeCompiler($class);
        $property = new ReflectionProperty($class, 'value');
        $type = $property->getType();
        static::assertNotNull($type);
        return [$compiler->field($property, $type), $compiler->unresolved];
    }
}
