<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ConstructorDecoder;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\EnumUnionLookup;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\FieldValueConverter;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Unit\Fixtures\DeferredValueEnum;
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
use ReflectionProperty;
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
#[CoversClass(ConstructorValueValidator::class)]
#[UsesClass(FieldPath::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(ConstructorParameter::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(FieldTypeResolver::class)]
#[UsesClass(FieldTypeValidator::class)]
#[UsesClass(MetadataCache::class)]
#[CoversClass(RootTypeValidator::class)]
#[CoversClass(PublicProperties::class)]
#[UsesClass(Field::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[UsesClass(FieldNameCollisions::class)]
#[CoversClass(EnumUnionLookup::class)]
#[UsesClass(FieldNames::class)]
final class AutoloadingTest extends TestCase
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

    /** @throws ReflectionException */
    public function testEnumUnionLookupPreservesBackingTypesAndLeavesOtherValuesUnmatched(): void
    {
        $target = new class {
            public \stdClass|NonBackedStatus|StringBackedStatus|IntBackedStatus|bool|null $value = null;
        };
        $lookup = new EnumUnionLookup(new ReflectionProperty($target, 'value')->getType());
        static::assertSame(StringBackedStatus::Ready, $lookup->find(StringBackedStatus::Ready->value));
        static::assertSame(StringBackedStatus::Pending, $lookup->find(StringBackedStatus::Pending->value));
        static::assertSame(IntBackedStatus::Ready, $lookup->find(IntBackedStatus::Ready->value));
        static::assertNull($lookup->find((string) IntBackedStatus::Ready->value));
        static::assertNull($lookup->find((float) IntBackedStatus::Ready->value));
        static::assertNull($lookup->find('unknown'));
        static::assertNull($lookup->find(true));
        static::assertNull($lookup->find(null));
        static::assertNull($lookup->find([]));
        static::assertNull(new EnumUnionLookup(null)->find('ready'));
        static::assertNull(new EnumUnionLookup(new ReflectionProperty(ScalarFields::class, 'string')->getType())->find(
            'ready',
        ));
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
        }
    }
}
