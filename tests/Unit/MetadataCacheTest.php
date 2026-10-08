<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\BackedEnumCaseFinder;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\CollectionTypeResolver;
use Eventjet\Json\Internal\CollectionTypeValidator;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorParameters;
use Eventjet\Json\Internal\ConstructorValidationPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\EnumFieldTypes;
use Eventjet\Json\Internal\EnumUnionValidator;
use Eventjet\Json\Internal\FieldCollectionUnionResolver;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\ListType;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NestedCollectionTypeResolver;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocItemTypeResolver;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeTokens;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\PublicPropertiesWithConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;

use function array_keys;
use function class_alias;

#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(MetadataCache::class)]
#[CoversClass(ConstructorParameter::class)]
#[CoversClass(ConstructorParameters::class)]
#[UsesClass(ConstructorValidationPlan::class)]
#[UsesClass(ConstructorValueValidator::class)]
#[CoversClass(ObjectTypeValidator::class)]
#[CoversClass(PublicProperties::class)]
#[UsesClass(PublicPropertyTypeValidator::class)]
#[CoversClass(BackedEnumCaseFinder::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[CoversClass(EnumFieldTypes::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(CollectionTypeResolver::class)]
#[UsesClass(CollectionTypeValidator::class)]
#[UsesClass(ListType::class)]
#[UsesClass(NestedCollectionTypeResolver::class)]
#[UsesClass(PhpDocFieldType::class)]
#[UsesClass(PhpDocItemTypeResolver::class)]
#[UsesClass(PhpDocType::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(PhpDocTypeTokens::class)]
#[CoversClass(FieldTypeResolver::class)]
#[CoversClass(FieldTypeValidator::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[UsesClass(ClassUnionValidator::class)]
#[UsesClass(EnumUnionValidator::class)]
#[UsesClass(FieldCollectionUnionResolver::class)]
final class MetadataCacheTest extends TestCase
{
    /** @throws ReflectionException */
    public function testGlobalNamespaceRelativeNamesHaveNoLeadingSeparator(): void
    {
        /** @var ReflectionClass<object> $class */
        $class = new ReflectionClass(stdClass::class);

        static::assertSame('stdClass', PhpDocClassNameResolver::resolve($class, 'namespace\stdClass'));
    }

    /**
     * @throws Exception
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
        $firstParameters = ConstructorParameters::resolve($firstClass);
        $secondParameters = ConstructorParameters::resolve($secondClass);

        $firstParameter = $firstParameters[0] ?? null;
        $secondParameter = $secondParameters[0] ?? null;
        static::assertNotNull($firstParameter);
        static::assertNotNull($secondParameter);
        static::assertSame('int', $firstParameter->typeName);
        static::assertTrue($firstParameter->builtin);
        static::assertSame('string', $secondParameter->typeName);
        static::assertTrue($secondParameter->builtin);
        static::assertNotSame($firstParameters, $secondParameters);
        static::assertSame($firstParameters, ConstructorParameters::resolve($firstClass));
        static::assertSame($secondParameters, ConstructorParameters::resolve($secondClass));

        $union = ConstructorParameters::resolve(new ReflectionClass(DistinctEnumScalarUnionField::class));
        $unionParameter = $union[0] ?? null;
        static::assertNotNull($unionParameter);
        static::assertSame(IntBackedStatus::class . '|string', $unionParameter->typeName);
        static::assertFalse($unionParameter->builtin);

        $scalarProperty = new ReflectionProperty(ScalarFields::class, 'string');
        $scalarType = EnumFieldTypes::resolve($scalarProperty);
        static::assertInstanceOf(ReflectionNamedType::class, $scalarType);
        static::assertSame($scalarType, EnumFieldTypes::resolve($scalarProperty));
    }

    /** @throws ReflectionException */
    public function testCachedEnumCasesPreserveBackingTypesAndRejectUnknownValues(): void
    {
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
                [NonBackedStatus::class, 1, null],
            ] as [$enum, $value, $expected]) {
                static::assertSame($expected, BackedEnumCaseFinder::forEnum($enum)->find($value));
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
            $load =
                /** @return array<never, never>|stdClass|false */
                static function () use ($counter, $metadata): array|stdClass|false {
                    ++$counter->calls;
                    return $metadata;
                };

            for ($lookup = 0; $lookup < 3; ++$lookup) {
                static::assertSame($metadata, $cache->resolve('field', $load));
            }
            static::assertSame(1, $counter->calls);
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    #[TestWith(['Named', ''])]
    public function testUnresolvedFieldTypesAreRetriedAfterDependencyLoads(string $name, string $suffix): void
    {
        $dependency = 'PublicMetadataDeferredClass' . $name;
        $enumDependency = 'MetadataCacheDeferredEnum' . $name;
        $class = CollectionDeclarationFixture::create($enumDependency, '', 'var');
        $field = new ReflectionProperty($class, 'value');

        static::assertNull(FieldTypeResolver::resolve($class, $field));
        static::assertTrue(class_alias(NonBackedStatus::class, $enumDependency));
        static::assertInstanceOf(DecodeError::class, FieldTypeResolver::resolve($class, $field));

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
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testResolvedNonCollectionDeclarationsAreCacheable(): void
    {
        foreach (['int', 'int|string', '\\' . NonBackedStatus::class . '|int'] as $declaration) {
            $class = CollectionDeclarationFixture::create($declaration, '', 'var');
            static::assertFalse(FieldTypeValidator::validate($class, new ReflectionProperty($class, 'value')));
        }

        $collectionClass = new ReflectionClass(CollectionDeclarationFixture::create('array', 'list<int>', 'param'));
        $collections = ObjectTypeValidator::validate($collectionClass, [], '');
        static::assertSame($collections, ObjectTypeValidator::validate($collectionClass, [], ''));

        $first = new class(1) {
            /** @var list<int> */
            public array $value;

            public function __construct(int $value)
            {
                $this->value = [$value];
            }
        };
        $second = new class {
            /** @var list<string> */
            public array $value = [];
        };
        $constructor = new ReflectionClass($first)->getConstructor();
        static::assertNotNull($constructor);
        $parameter = $constructor->getParameters()[0] ?? null;
        static::assertNotNull($parameter);
        $firstProperty = new ReflectionProperty($first, 'value');
        $secondProperty = new ReflectionProperty($second, 'value');

        for ($lookup = 0; $lookup < 2; ++$lookup) {
            static::assertNull(FieldTypeResolver::resolve($first::class, $parameter));
            $firstType = FieldTypeResolver::resolve($first::class, $firstProperty);
            $secondType = FieldTypeResolver::resolve($second::class, $secondProperty);
            static::assertInstanceOf(ListType::class, $firstType);
            static::assertInstanceOf(ListType::class, $secondType);
            static::assertSame('int', $firstType->itemType);
            static::assertSame('string', $secondType->itemType);
            static::assertSame($firstType, FieldTypeResolver::resolve($first::class, $firstProperty));
            static::assertSame($secondType, FieldTypeResolver::resolve($second::class, $secondProperty));
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function testUnresolvedUnionMetadataIsRetriedAfterDependencyLoads(): void
    {
        $dependency = 'MetadataCacheDeferredUnionClass';
        $class = CollectionDeclarationFixture::create($dependency . '|int', '', 'var');
        $field = new ReflectionProperty($class, 'value');

        static::assertNull(FieldTypeResolver::resolve($class, $field));
        static::assertTrue(class_alias(ParentClassFieldBase::class, $dependency));
        static::assertInstanceOf(DecodeError::class, FieldTypeResolver::resolve($class, $field));

        $untypedClass = CollectionDeclarationFixture::create('', '', 'var');
        $untyped = new ReflectionProperty($untypedClass, 'value');
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            static::assertFalse(EnumFieldTypes::resolve($untyped));
            static::assertNull(BackedEnumValueConverter::convert($untypedClass, $untyped, 'value', 'value'));
        }

        $enumDependency = 'EnumConversionDeferredUnion';
        $enumClass = CollectionDeclarationFixture::create($enumDependency . '|int', '', 'var');
        $enumField = new ReflectionProperty($enumClass, 'value');
        static::assertNull(BackedEnumValueConverter::convert(
            $enumClass,
            $enumField,
            StringBackedStatus::Ready->value,
            'value',
        ));
        static::assertTrue(class_alias(StringBackedStatus::class, $enumDependency));
        static::assertSame(StringBackedStatus::Ready, BackedEnumValueConverter::convert(
            $enumClass,
            $enumField,
            StringBackedStatus::Ready->value,
            'value',
        ));
    }
}
