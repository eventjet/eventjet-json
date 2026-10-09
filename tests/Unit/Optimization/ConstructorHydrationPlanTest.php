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
use Eventjet\Json\Internal\DeclaredClassType;
use Eventjet\Json\Internal\EnumFieldTypes;
use Eventjet\Json\Internal\EnumUnionLookup;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\FieldValueConverter;
use Eventjet\Json\Internal\MappedConstructorPlan;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;

use function class_alias;

#[CoversClass(FieldValueConverter::class)]
#[CoversClass(DeclaredClassType::class)]
#[CoversClass(ConstructorDecoder::class)]
#[CoversClass(ConstructorPlan::class)]
#[CoversClass(ConstructorValueValidator::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(EnumFieldTypes::class)]
#[CoversClass(EnumUnionLookup::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[UsesClass(DecodeError::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\EnumUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldCollectionUnionResolver::class)]
#[CoversClass(ConstructorParameter::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(FieldTypeResolver::class)]
#[UsesClass(FieldTypeValidator::class)]
#[UsesClass(MetadataCache::class)]
#[CoversClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(FieldNames::class)]
#[CoversClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(MappedConstructorPlan::class)]
#[CoversClass(FieldNameCollisions::class)]
#[CoversClass(ObjectHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyTypeValidator::class)]
final class ConstructorHydrationPlanTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testClassFieldValidationCachesOnlySupportedLoadedTypes(): void
    {
        $class = ScalarFields::class;
        $cache = new ReflectionProperty(ClassFieldTypeValidator::class, 'validated');
        static::assertNull(ClassFieldTypeValidator::validate($class, 'first', $class));
        /** @var array<string, null> $validated */
        $validated = $cache->getValue();
        static::assertArrayHasKey($class, $validated);
        static::assertNull(ClassFieldTypeValidator::validate($class, 'second', $class));

        $dependency = __NAMESPACE__ . '\\LateClassFieldValidationTarget';
        static::assertNull(ClassFieldTypeValidator::validate($class, 'first', $dependency));
        /** @var array<string, null> $validated */
        $validated = $cache->getValue();
        static::assertArrayNotHasKey($dependency, $validated);
        static::assertTrue(class_alias(ScalarFields::class, $dependency));
        static::assertNull(ClassFieldTypeValidator::validate($class, 'second', $dependency));
        /** @var array<string, null> $validated */
        $validated = $cache->getValue();
        static::assertArrayHasKey($dependency, $validated);

        foreach (['first', 'second'] as $field) {
            static::assertEquals(
                DecodeError::nonInstantiableField($class, $field, 'interface', JsonSerializable::class),
                ClassFieldTypeValidator::validate($class, $field, JsonSerializable::class),
            );
        }
        /** @var array<string, null> $validated */
        $validated = $cache->getValue();
        static::assertArrayNotHasKey(JsonSerializable::class, $validated);
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

    /** @throws ReflectionException */
    public function testMappedScalarHydrationKeepsJsonNamesAfterWarming(): void
    {
        $class = MappedDefaults::class;
        $input = new stdClass();
        $input->{'$ref'} = 'next';
        $input->count = 9;
        $invalid = new stdClass();
        $invalid->{'$ref'} = 42;
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            static::assertEquals(new MappedDefaults(ref: 'next', count: 9), ObjectHydrator::hydrate($class, $input));
            static::assertNull(ConstructorDecoder::cachedPlan($class));
            static::assertEquals(new MappedDefaults(), ObjectHydrator::hydrate($class, new stdClass()));
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class, 'nested.$ref', 'string|null', 42),
                ObjectHydrator::hydrate($class, $invalid, 'nested'),
            );
        }
    }
}
