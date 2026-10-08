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
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Cases\CollectionNameSource;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

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
#[CoversClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(MappedConstructorPlan::class)]
#[CoversClass(FieldNameCollisions::class)]
#[CoversClass(ObjectHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyTypeValidator::class)]
#[CoversClass(FieldNames::class)]
final class ConstructorValidationPlanTest extends TestCase
{
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

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    public function testMappedPlansKeepInputNamesAndArgumentsSeparate(): void
    {
        $class = new ReflectionClass(MappedDefaults::class);
        for ($lookup = 0; $lookup < 3; ++$lookup) {
            static::assertSame(
                ['ref' => 'next', 'count' => 9],
                ConstructorDecoder::convert($class, ['$ref' => 'next', 'count' => 9], ''),
            );
            static::assertSame(['ref' => null], ConstructorDecoder::convert($class, ['$ref' => null], ''));
            static::assertSame([], ConstructorDecoder::convert($class, ['ref' => 'ignored'], ''));
            static::assertNull(ConstructorDecoder::scalarPlan($class->getName()));
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class->getName(), 'nested.$ref', 'string|null', 42),
                ConstructorDecoder::convert($class, ['$ref' => 42], 'nested'),
            );
        }
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws Exception
     */
    public function testMappedConversionErrorsSurviveColdAndCachedPlans(): void
    {
        $target = new class implements \JsonSerializable {
            public function __construct(
                #[Field('state')]
                public StringBackedStatus $value = StringBackedStatus::Ready,
            ) {}

            #[\Override]
            public function jsonSerialize(): object
            {
                return (object) ['state' => $this->value];
            }
        };
        $class = new ReflectionClass($target);
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            $error = ConstructorDecoder::convert($class, ['state' => 'unknown'], 'nested');
            static::assertInstanceOf(DecodeError::class, $error);
            static::assertStringContainsString('nested.state', $error->getMessage());
            static::assertSame(
                ['value' => StringBackedStatus::Ready],
                ConstructorDecoder::convert($class, ['state' => StringBackedStatus::Ready->value], ''),
            );
        }
    }

    /**
     * @throws ReflectionException
     * @throws DecodeError
     * @throws RuntimeException
     */
    public function testMappedSerializationDoesNotExposeNonPublicState(): void
    {
        $class = CollectionNameSource::load(
            'MappedPrivateState',
            'use Eventjet\\Json\\Field; final class MappedPrivateState implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; private string $secret = "private"; protected string $hidden = "protected"; public static string $shared = "static"; #[Field("wire")] public string $value = "public"; }',
        );
        $object = new ReflectionClass($class)->newInstance();
        static::assertEquals((object) ['wire' => 'public'], MappedObjectSerializer::serialize($object));
        static::assertEquals((object) ['wire' => 'public'], MappedObjectSerializer::serialize($object));
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws RuntimeException
     */
    public function testInvalidMappingsAreRejectedBeforeSerializationOrHydration(): void
    {
        $class = CollectionNameSource::load(
            'InvalidMappedSerializer',
            'use Eventjet\\Json\\Field; final class InvalidMappedSerializer implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; public static string $shared = ""; #[Field("ref")] public string $value = ""; public string $ref = ""; }',
        );
        $reflection = new ReflectionClass($class);
        $error = DecodeError::nonInstantiableTarget(
            $class,
            'Properties value and ref use the same JSON field name "ref".',
        );
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            static::assertEquals($error, RootTypeValidator::validate($reflection));
            static::assertEquals($error, ConstructorDecoder::convert($reflection, [], ''));
            static::assertEquals($error, PublicProperties::resolve($reflection));
            try {
                MappedObjectSerializer::serialize($reflection->newInstance());
                static::fail('Invalid mappings must not serialize.');
            } catch (DecodeError $caught) {
                static::assertSame($error->getMessage(), $caught->getMessage());
            }
        }
    }

    /**
     * @throws RuntimeException
     * @throws ReflectionException
     * @throws JsonException
     */
    public function testPrivateAncestorMappingsAreRejected(): void
    {
        foreach (['', 'static '] as $modifier) {
            foreach ([1, 2] as $depth) {
                $name = 'PrivateAncestorMapping' . ($modifier === '' ? 'Instance' : 'Static') . $depth;
                $parent = $name . 'Base';
                $source =
                    'use Eventjet\\Json\\Field; class '
                    . $parent
                    . ' { #[Field("hidden")] private '
                    . $modifier
                    . 'string $value = "secret"; }';
                if ($depth === 2) {
                    $source .= ' class ' . $name . 'Middle extends ' . $parent . ' {}';
                    $parent = $name . 'Middle';
                }
                $class = CollectionNameSource::load(
                    $name,
                    $source
                    . ' final class '
                    . $name
                    . ' extends '
                    . $parent
                    . ' implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; #[Field("wire")] public string $value = "public"; }',
                );
                $reflection = new ReflectionClass($class);
                $error = DecodeError::nonInstantiableTarget(
                    $class,
                    '#[Field] requires a public instance property; value is not one.',
                );
                for ($attempt = 0; $attempt < 2; ++$attempt) {
                    static::assertEquals($error, RootTypeValidator::validate($reflection));
                    static::assertEquals($error, ConstructorDecoder::convert($reflection, [], ''));
                    static::assertEquals($error, PublicProperties::resolve($reflection));
                    try {
                        MappedObjectSerializer::serialize($reflection->newInstance());
                        static::fail('Private ancestor mappings must not serialize.');
                    } catch (DecodeError $caught) {
                        static::assertSame($error->getMessage(), $caught->getMessage());
                    }
                }
            }
        }
    }

    /**
     * @throws RuntimeException
     * @throws ReflectionException
     * @throws DecodeError
     */
    public function testAncestorDiscoveryPreservesEffectivePublicMappings(): void
    {
        $class = CollectionNameSource::load(
            'EffectiveMappedChild',
            'use Eventjet\\Json\\Field; class EffectiveMappedParent { private string $secret = "hidden"; #[Field("old")] public string $value = "parent"; #[Field("inherited")] public string $other = "kept"; } final class EffectiveMappedChild extends EffectiveMappedParent implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; #[Field("wire")] public string $value = "child"; public string $old = "ordinary"; }',
        );
        $reflection = new ReflectionClass($class);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            static::assertSame(['value' => 'wire', 'other' => 'inherited'], RootTypeValidator::fieldNames($reflection));
            static::assertEquals(
                (object) ['wire' => 'child', 'inherited' => 'kept', 'old' => 'ordinary'],
                MappedObjectSerializer::serialize($reflection->newInstance()),
            );
        }
    }
}
