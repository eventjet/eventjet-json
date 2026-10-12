<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\CollectionUnionType;
use Eventjet\Json\Internal\CollectionUnionTypeValidator;
use Eventjet\Json\Internal\ConstructorDecoder;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\EnumUnionLookup;
use Eventjet\Json\Internal\EnumUnionValidator;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\FieldValueConverter;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyHydrator;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\NestedSelfCollections;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Unit\Fixtures\DeferredListItem;
use Eventjet\Json\Test\Unit\Fixtures\DeferredValueEnum;
use Eventjet\Json\Test\Unit\Fixtures\PropertyCountingReflection;
use JsonException;
use JsonSerializable;
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
use function spl_autoload_register;
use function spl_autoload_unregister;

#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(FieldValueConverter::class)]
#[CoversClass(ConstructorDecoder::class)]
#[CoversClass(ConstructorPlan::class)]
#[CoversClass(\Eventjet\Json\Internal\ListValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\ListInputNormalizer::class)]
#[UsesClass(\Eventjet\Json\Internal\ListType::class)]
#[CoversClass(ConstructorValueValidator::class)]
#[UsesClass(FieldPath::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(ConstructorParameter::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralFieldType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralMarker::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionUnionValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\EnumUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldCollectionUnionMemberResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldCollectionUnionResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldCollectionUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\NestedCollectionType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralTags::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralTypeTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralParameter::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[UsesClass(FieldTypeResolver::class)]
#[UsesClass(FieldTypeValidator::class)]
#[UsesClass(MetadataCache::class)]
#[CoversClass(RootTypeValidator::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(ObjectHydrator::class)]
#[UsesClass(Field::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[UsesClass(FieldNameCollisions::class)]
#[CoversClass(EnumUnionLookup::class)]
#[CoversClass(CollectionUnionTypeValidator::class)]
#[UsesClass(CollectionUnionType::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionTypeValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionUnionShapeValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\EnumFieldTypes::class)]
#[UsesClass(FieldNames::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteral::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralNumber::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldType::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralField::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralFieldValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralScalarConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\NestedCollectionTypeResolver::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocItemTypeResolver::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocType::class)]
#[UsesClass(\Eventjet\Json\Internal\ConcreteClassValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\ScalarListValueConverter::class)]
#[CoversClass(\Eventjet\Json\Internal\ClassTypeDependencies::class)]
final class AutoloadingTest extends TestCase
{
    /** @throws ReflectionException */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOrdinaryScalarPhpDocDoesNotLoadLiteralResolution(): void
    {
        $object = new class {
            /** @var positive-int */
            public int $value = 42;
            /** Defaults to 42, never 'null', true, false, or Example::VALUE. */
            public int $described = 42;
            public int $undocumented = 42;
        };

        static::assertFalse(FieldTypeResolver::literal(new \ReflectionProperty($object, 'undocumented')));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralMarker::class, autoload: false));
        foreach (['value', 'described'] as $name) {
            static::assertFalse(FieldTypeResolver::literal(new \ReflectionProperty($object, $name)));
        }
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralTags::class, autoload: false));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralFieldType::class, autoload: false));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralField::class, autoload: false));
    }

    /** @throws ReflectionException */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNullableClassCollectionsDoNotLoadUnneededUnionValidators(): void
    {
        static::assertNull(CollectionUnionTypeValidator::validate(
            stdClass::class,
            'items',
            new CollectionUnionType([NestedSelfCollections::class, 'null']),
        ));
        static::assertFalse(class_exists(ClassUnionValidator::class, autoload: false));
        static::assertFalse(class_exists(EnumUnionValidator::class, autoload: false));
        static::assertEquals(
            DecodeError::nonInstantiableField(stdClass::class, 'items', 'interface', JsonSerializable::class),
            CollectionUnionTypeValidator::validate(
                stdClass::class,
                'items',
                new CollectionUnionType([StringBackedStatus::class, JsonSerializable::class]),
            ),
        );
    }

    /** @throws ReflectionException */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConstructorOnlyObjectsDoNotLoadPropertyAssignmentCode(): void
    {
        $target = new class('expected') {
            public function __construct(
                public string $value,
            ) {}
        };
        $input = new stdClass();
        $input->value = 'expected';
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            static::assertEquals($target, ObjectHydrator::hydrate($target::class, $input));
            static::assertFalse(class_exists(PublicPropertyHydrator::class, autoload: false));
            static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralMarker::class, autoload: false));
            static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralField::class, autoload: false));
            static::assertFalse(class_exists(
                \Eventjet\Json\Internal\PhpDocLiteralFieldValidator::class,
                autoload: false,
            ));
        }
        $collection = new class([42]) {
            /** @param list<int> $values The name has a native scalar type. */
            public function __construct(
                public array $values,
                public string $name = '',
            ) {}
        };
        $collectionInput = new stdClass();
        $collectionInput->values = [42];
        static::assertSame(
            [],
            iterator_to_array(\Eventjet\Json\Internal\ClassTypeDependencies::field(
                new \ReflectionProperty($collection, 'values'),
                new \Eventjet\Json\Internal\ListType('int', false),
            )),
        );
        static::assertEquals($collection, ObjectHydrator::hydrate($collection::class, $collectionInput));
        $nullable = new class(null) {
            /** @param list<int>|null $values */
            public function __construct(
                public array|null $values,
            ) {}
        };
        $nullableInput = new stdClass();
        $nullableInput->values = null;
        static::assertEquals($nullable, ObjectHydrator::hydrate($nullable::class, $nullableInput));
        $described = new class('expected') {
            /** Accepts 1 value: never 'null', true, false, or Example::VALUE. */
            public function __construct(
                public string $value,
            ) {}
        };
        static::assertEquals($described, ObjectHydrator::hydrate($described::class, $input));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralField::class, autoload: false));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteral::class, autoload: false));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralTags::class, autoload: false));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralParameter::class, autoload: false));
        static::assertFalse(class_exists(\Eventjet\Json\Internal\PhpDocLiteralTypeParser::class, autoload: false));
        $ordinary = new class(new EmptyObject()) {
            /** @param \Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject $object */
            public function __construct(
                public EmptyObject $object,
            ) {}
        };
        $ordinaryInput = new stdClass();
        $ordinaryInput->object = new stdClass();
        static::assertEquals($ordinary, ObjectHydrator::hydrate($ordinary::class, $ordinaryInput));
        static::assertFalse(class_exists(PhpDocClassNameResolver::class, autoload: false));
    }

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
            static::assertSame(
                [],
                \Eventjet\Json\Internal\ListValueConverter::convert(
                    stdClass::class,
                    'items',
                    new \Eventjet\Json\Internal\ListType(DeferredListItem::class, false),
                    [],
                ),
            );
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
            static::assertSame($values, ConstructorDecoder::convert($class, $values, ''));
            foreach ($values as $name => $value) {
                $parameter = new ReflectionParameter([$class->getName(), '__construct'], $name);
                $type = $parameter->getType();
                static::assertInstanceOf(ReflectionNamedType::class, $type);
                static::assertNotNull(ConstructorValueValidator::forParameter(
                    new ConstructorParameter($parameter, $class),
                    [],
                ));
                static::assertSame($value, new FieldValueConverter($parameter, null)->convert(
                    $class->getName(),
                    $value,
                    $name,
                ));
                $property = $class->getProperty($name);
                static::assertSame($value, new FieldValueConverter($property, null)->convert(
                    $class->getName(),
                    $value,
                    $name,
                ));
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
            static::assertInstanceOf(DecodeError::class, BackedEnumValueConverter::convertValue(
                DeferredValueEnum::class,
                'value',
                DeferredValueEnum::class,
                1,
            ));
            static::assertNull(BackedEnumValueConverter::convertUnion(
                DeferredValueEnum::class,
                'value',
                [DeferredValueEnum::class, 'int'],
                1,
            ));
            static::assertNotContains(DeferredEnumBacking::class, $requests->names);
            $converted = BackedEnumValueConverter::convertValue(
                DeferredValueEnum::class,
                'value',
                DeferredValueEnum::class,
                'ready',
            );
            static::assertSame(DeferredValueEnum::Ready, $converted);
            static::assertContains(DeferredEnumBacking::class, $requests->names);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws TypeError
     */
    public function testConstructorValueChecksOnlyAutoloadPresentClasses(): void
    {
        $dependency = 'ConstructorValueDeferredClass';
        $class = new ReflectionClass(CollectionDeclarationFixture::create($dependency, '', 'param'));
        $parameter = new ConstructorParameter(
            new ReflectionParameter([$class->getName(), '__construct'], 'value'),
            $class,
        );
        $requests = new class {
            /** @var list<string> */
            public array $names = [];

            /** @return list<string> */
            public function snapshot(): array
            {
                return $this->names;
            }
        };
        $autoload = static function (string $name) use ($requests): void {
            $requests->names[] = $name;
        };
        spl_autoload_register($autoload);
        try {
            static::assertNotNull(ConstructorValueValidator::forParameter($parameter, []));
            static::assertSame([], $requests->snapshot());
            static::assertNotNull(ConstructorValueValidator::forParameter($parameter, ['value' => null]));
            static::assertSame([$dependency], $requests->snapshot());
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
            static::assertSame($expected, RootTypeValidator::fieldNames($class));
            static::assertSame($expected, RootTypeValidator::fieldNames($class));
            static::assertSame(1, $class->propertyLookups);
            static::assertSame(1, $class->parentLookups);
        }
    }
}
