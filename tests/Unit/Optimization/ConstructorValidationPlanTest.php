<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ConstructorDecoder;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\EnumFieldTypes;
use Eventjet\Json\Internal\EnumUnionLookup;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\FieldValueConverter;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use stdClass;

use function class_alias;

#[CoversClass(FieldValueConverter::class)]
#[CoversClass(ConstructorDecoder::class)]
#[CoversClass(ConstructorPlan::class)]
#[CoversClass(ConstructorValueValidator::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(EnumFieldTypes::class)]
#[CoversClass(EnumUnionLookup::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[CoversClass(ConstructorParameter::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(FieldTypeResolver::class)]
#[UsesClass(FieldTypeValidator::class)]
#[UsesClass(MetadataCache::class)]
#[UsesClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(ObjectHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicProperties::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyTypeValidator::class)]
final class ConstructorValidationPlanTest extends TestCase
{
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
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     */
    public function testScalarHydrationReusesPlansButRechecksValuesAndDefaults(): void
    {
        $target = new class {
            public function __construct(
                public int $value = 1,
            ) {}
        };
        $class = $target::class;
        static::assertNull(ConstructorDecoder::cachedPlan($class));
        $input = new stdClass();
        $input->value = 2;
        $target->value = 2;
        $first = ObjectHydrator::hydrate($class, $input);
        static::assertEquals($target, $first);
        $plan = ConstructorDecoder::cachedPlan($class);
        static::assertInstanceOf(ConstructorPlan::class, $plan);
        static::assertTrue($plan->scalarOnly);
        $cache = new ReflectionProperty(ObjectHydrator::class, 'validatedClasses');
        static::assertIsArray($cache->getValue());
        static::assertSame($plan, $cache->getValue()[$class] ?? null);
        $input->value = 3;
        $target->value = 3;
        static::assertEquals($target, ObjectHydrator::hydrate($class, $input));
        $target->value = 1;
        static::assertEquals($target, ObjectHydrator::hydrate($class, new stdClass()));
        $input->value = null;
        static::assertEquals(
            DecodeError::fieldTypeMismatch($class, 'nested.value', 'int', null),
            ObjectHydrator::hydrate($class, $input, 'nested'),
        );
        static::assertSame($plan, ConstructorDecoder::cachedPlan($class));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testPublicPropertiesKeepTheirValidationAfterWarming(): void
    {
        $target = new class {
            public string $label = '';

            public function __construct(
                public int $value = 1,
            ) {}
        };
        $class = $target::class;
        $input = new stdClass();
        $input->label = 'first';
        $target->label = 'first';
        static::assertEquals($target, ObjectHydrator::hydrate($class, $input));
        $cache = new ReflectionProperty(ObjectHydrator::class, 'validatedClasses');
        static::assertIsArray($cache->getValue());
        static::assertInstanceOf(ReflectionClass::class, $cache->getValue()[$class] ?? null);
        $target->label = 'second';
        $input->label = 'second';
        static::assertEquals($target, ObjectHydrator::hydrate($class, $input));
        $input->label = false;
        static::assertEquals(
            DecodeError::fieldTypeMismatch($class, 'nested.label', 'string', false),
            ObjectHydrator::hydrate($class, $input, 'nested'),
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     */
    public function testNonScalarHydrationReusesPlansAndRechecksValues(): void
    {
        $target = new class {
            public function __construct(
                public StringBackedStatus|bool|null $value = null,
            ) {}
        };
        $class = $target::class;
        static::assertNull(ConstructorDecoder::cachedPlan($class));
        $input = new stdClass();
        $input->value = StringBackedStatus::Ready->value;
        $target->value = StringBackedStatus::Ready;
        static::assertEquals($target, ObjectHydrator::hydrate($class, $input));
        $plan = ConstructorDecoder::cachedPlan($class);
        static::assertInstanceOf(ConstructorPlan::class, $plan);
        static::assertFalse($plan->scalarOnly);
        $cache = new ReflectionProperty(ObjectHydrator::class, 'validatedClasses');
        static::assertIsArray($cache->getValue());
        static::assertSame($plan, $cache->getValue()[$class] ?? null);

        foreach ([false, null, StringBackedStatus::Pending] as $value) {
            $target->value = $value;
            $input->value = $value instanceof StringBackedStatus ? $value->value : $value;
            static::assertEquals($target, ObjectHydrator::hydrate($class, $input));
        }
        $target->value = null;
        static::assertEquals($target, ObjectHydrator::hydrate($class, new stdClass()));
        $input->value = [];
        $error = ObjectHydrator::hydrate($class, $input, 'nested');
        static::assertInstanceOf(DecodeError::class, $error);
        static::assertStringContainsString('Field nested.value must be of type', $error->getMessage());
        static::assertSame($plan, ConstructorDecoder::cachedPlan($class));
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
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
                ConstructorDecoder::convert($class, ['first' => false, 'second' => 1], 'before'),
            );
            static::assertSame([], ConstructorDecoder::convert($class, [], ''));
            static::assertSame(
                ['first' => null, 'second' => 'valid'],
                ConstructorDecoder::convert($class, ['first' => null, 'second' => 'valid'], ''),
            );
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class->getName(), 'after.second', 'string', 1),
                ConstructorDecoder::convert($class, ['first' => 1, 'second' => 1], 'after'),
            );
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     * @throws JsonException
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
                ConstructorDecoder::convert($class, ['first' => false], ''),
            );
            static::assertInstanceOf(DecodeError::class, ConstructorDecoder::convert($class, ['first' => 1], ''));
        }

        $invalidTarget = new class {
            public static bool $value;

            public function __construct(mixed $value = null)
            {
                self::$value = $value !== null;
            }
        };
        $invalidClass = new ReflectionClass($invalidTarget);
        static::assertEquals(
            DecodeError::nonInstantiableField(
                $invalidClass->getName(),
                'value',
                'unsupported type',
                'mixed',
                '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            ),
            ConstructorDecoder::convert($invalidClass, [], ''),
        );

        $enumTarget = new class {
            public function __construct(
                public StringBackedStatus $first = StringBackedStatus::Ready,
                public int $second = 1,
            ) {}
        };
        $enumClass = new ReflectionClass($enumTarget);
        for ($lookup = 0; $lookup < 3; ++$lookup) {
            static::assertEquals(
                DecodeError::fieldTypeMismatch($enumClass->getName(), 'nested.second', 'int', false),
                ConstructorDecoder::convert($enumClass, ['first' => 'unknown', 'second' => false], 'nested'),
            );
            static::assertSame(
                ['first' => StringBackedStatus::Ready, 'second' => 1],
                ConstructorDecoder::convert(
                    $enumClass,
                    ['first' => StringBackedStatus::Ready->value, 'second' => 1],
                    '',
                ),
            );
            static::assertInstanceOf(DecodeError::class, ConstructorDecoder::convert(
                $enumClass,
                ['first' => 'unknown', 'second' => 1],
                '',
            ));
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws JsonException
     */
    public function testConstructorPlansRetryUnresolvedDependencies(): void
    {
        $dependency = 'ConstructorPlanDeferredEnum';
        $class = new ReflectionClass(CollectionDeclarationFixture::create($dependency, '', 'param'));
        static::assertSame([], ConstructorDecoder::convert($class, [], ''));
        static::assertTrue(class_alias(NonBackedStatus::class, $dependency));
        static::assertInstanceOf(DecodeError::class, ConstructorDecoder::convert($class, [], ''));
    }
}
