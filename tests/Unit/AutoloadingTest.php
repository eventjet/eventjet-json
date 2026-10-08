<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\BackedEnumCaseFinder;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorParameters;
use Eventjet\Json\Internal\ConstructorValidationPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NamedFieldValueConverter;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PublicPropertyNamedValueConverter;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Unit\Fixtures\DeferredValueEnum;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
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

use function array_fill_keys;
use function array_keys;
use function class_alias;
use function class_exists;
use function enum_exists;
use function spl_autoload_register;
use function spl_autoload_unregister;

#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(NamedFieldValueConverter::class)]
#[CoversClass(ObjectTypeValidator::class)]
#[CoversClass(ConstructorValidationPlan::class)]
#[CoversClass(ConstructorValueValidator::class)]
#[UsesClass(FieldPath::class)]
#[CoversClass(PublicPropertyNamedValueConverter::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[UsesClass(BackedEnumCaseFinder::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(ConstructorParameter::class)]
#[UsesClass(ConstructorParameters::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(FieldTypeResolver::class)]
#[UsesClass(FieldTypeValidator::class)]
#[UsesClass(MetadataCache::class)]
#[UsesClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
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
            static::assertSame(
                array_fill_keys(array_keys($values), value: null),
                ObjectTypeValidator::validate($class, $values, ''),
            );
            foreach ($values as $name => $value) {
                $parameter = new ReflectionParameter([$class->getName(), '__construct'], $name);
                $type = $parameter->getType();
                static::assertInstanceOf(ReflectionNamedType::class, $type);
                static::assertNotNull(ConstructorValueValidator::forParameter(
                    new ConstructorParameter($parameter, $class),
                    [],
                ));
                static::assertSame($value, NamedFieldValueConverter::convert(
                    $class->getName(),
                    new ConstructorParameter($parameter, $class),
                    $value,
                    $name,
                ));
                $property = $class->getProperty($name);
                static::assertSame(
                    ['property' => $property, 'value' => $value],
                    PublicPropertyNamedValueConverter::convert(
                        $class->getName(),
                        $property,
                        $type,
                        ['collection' => null, 'path' => $name, 'typeName' => $type->getName()],
                        $value,
                    ),
                );
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

    /** @throws ReflectionException */
    public function testConstructorPlansRecheckValuesAndPathsAfterWarming(): void
    {
        $target = new class {
            public function __construct(
                public int|null $first = null,
                public string $second = '',
            ) {}
        };
        $class = new ReflectionClass($target);
        for ($lookup = 0; $lookup < 3; ++$lookup) {
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class->getName(), 'before.first', 'int|null', false),
                ObjectTypeValidator::validate($class, ['first' => false, 'second' => 1], 'before'),
            );
            static::assertSame(['first' => null, 'second' => null], ObjectTypeValidator::validate($class, [], ''));
            static::assertSame(
                ['first' => null, 'second' => null],
                ObjectTypeValidator::validate($class, ['first' => null, 'second' => 'valid'], ''),
            );
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class->getName(), 'after.second', 'string', 1),
                ObjectTypeValidator::validate($class, ['first' => 1, 'second' => 1], 'after'),
            );
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     */
    public function testConstructorErrorsRetainParameterOrder(): void
    {
        $target = new class {
            public function __construct(
                public int $first = 1,
                public mixed $second = null,
            ) {}
        };
        $class = new ReflectionClass($target);
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class->getName(), 'first', 'int', false),
                ObjectTypeValidator::validate($class, ['first' => false], ''),
            );
            static::assertInstanceOf(DecodeError::class, ObjectTypeValidator::validate($class, ['first' => 1], ''));
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function testConstructorPlansRetryUnresolvedDependencies(): void
    {
        $dependency = 'ConstructorPlanDeferredEnum';
        $class = new ReflectionClass(CollectionDeclarationFixture::create($dependency, '', 'param'));
        static::assertSame(['value' => null], ObjectTypeValidator::validate($class, [], ''));
        static::assertTrue(class_alias(NonBackedStatus::class, $dependency));
        static::assertInstanceOf(DecodeError::class, ObjectTypeValidator::validate($class, [], ''));
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
}
