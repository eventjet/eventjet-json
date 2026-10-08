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
#[CoversClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(MappedConstructorPlan::class)]
#[CoversClass(FieldNameCollisions::class)]
#[CoversClass(ObjectHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyHydrator::class)]
#[UsesClass(\Eventjet\Json\Internal\PublicPropertyTypeValidator::class)]
#[CoversClass(FieldNames::class)]
#[CoversClass(\Eventjet\Json\Internal\DirectJsonParser::class)]
#[CoversClass(\Eventjet\Json\Internal\DirectScalarPlan::class)]
#[CoversClass(\Eventjet\Json\Internal\DirectListPlan::class)]
#[CoversClass(\Eventjet\Json\Json::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionTypeValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\ListInputNormalizer::class)]
#[UsesClass(\Eventjet\Json\Internal\ListType::class)]
#[UsesClass(\Eventjet\Json\Internal\MapType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenStream::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\ListValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\MapDecodeError::class)]
#[UsesClass(\Eventjet\Json\Internal\MapTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\NestedCollectionType::class)]
#[UsesClass(\Eventjet\Json\Internal\NestedCollectionTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocClassNameResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImportScanner::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImportStatement::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImports::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocItemTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNamespaceDeclaration::class)]
#[UsesClass(\Eventjet\Json\Internal\ScalarListValueConverter::class)]
/**
 * @mago-expect lint:cyclomatic-complexity Parameterized declaration and grammar checks exercise independent eligibility boundaries.
 * @mago-expect lint:too-many-methods Constructor planning tests share their existing coverage metadata.
 */
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
            static::assertNull(ConstructorDecoder::cachedPlan($class->getName()));
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
                #[Field('0')]
                public StringBackedStatus $value = StringBackedStatus::Ready,
            ) {}

            #[\Override]
            public function jsonSerialize(): object
            {
                return (object) ['0' => $this->value];
            }
        };
        $class = new ReflectionClass($target);
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            $error = ConstructorDecoder::convert($class, ['0' => 'unknown'], 'nested');
            static::assertInstanceOf(DecodeError::class, $error);
            static::assertStringContainsString('nested.0', $error->getMessage());
            static::assertSame(
                ['value' => StringBackedStatus::Ready],
                ConstructorDecoder::convert($class, ['0' => StringBackedStatus::Ready->value], ''),
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

    /** @throws \ReflectionException|\JsonException|Exception|UnknownClassOrInterfaceException */
    public function testDirectScalarPlansValidateBeforeConstructingAndPreserveScalarValues(): void
    {
        $plan = \Eventjet\Json\Internal\DirectJsonParser::compile(ScalarFields::class);
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $plan);
        for ($index = -20; $index <= 20; ++$index) {
            $expected = new ScalarFields('record-' . $index, $index, $index / 4, ($index % 2) === 0);
            $json = json_encode($expected, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            static::assertEquals($expected, $plan->decode($json));
            static::assertEquals($expected, $plan->decode(" \t\r\n" . $json . "\r\n "));
            static::assertEquals(
                $expected,
                $plan->decode(str_replace(['{', ':', ',', '}'], ["{\n", " \r : \t", " \t,\n", "\n}"], $json)),
            );
        }
        $nullable = \Eventjet\Json\Internal\DirectJsonParser::compile(
            \Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields::class,
        );
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $nullable);
        static::assertEquals(
            new \Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields(null, null, null, null, null),
            $nullable->decode('{"string":null,"integer":null,"float":null,"boolean":null,"null":null}'),
        );
        static::assertEquals(
            new \Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields('', 0, 0.0, false, null),
            $nullable->decode('{"string":"","integer":-0,"float":-0,"boolean":false,"null":null}'),
        );
        static::assertSame('{"string":"","integer":0,"float":0.0,"boolean":false}', json_encode(
            $plan->decode('{"string":"","integer":0,"float":-0,"boolean":false}'),
            JSON_PRESERVE_ZERO_FRACTION,
        ));
        static::assertSame('{"string":"","integer":0,"float":-0.0,"boolean":false}', json_encode(
            $plan->decode('{"string":"","integer":0,"float":-0.0,"boolean":false}'),
            JSON_PRESERVE_ZERO_FRACTION,
        ));
        foreach ([
            '',
            '{}',
            '[]',
            '{"string":"x","integer":1,"float":1,"boolean":true} trailing',
            '{"string":"x","integer":1,"float":1,"boolean":true,}',
            '{"string":"x","integer":1.1,"float":1,"boolean":true}',
            '{"string":"x","integer":01,"float":1,"boolean":true}',
            '{"string":"x","integer":1000000000000000000,"float":1,"boolean":true}',
            '{"string":"x","integer":1,"float":1,"boolean":null}',
            '{"string":"x","integer":1,"float":1,"boolean":1}',
            '{"string":"\\u0041","integer":1,"float":1,"boolean":true}',
            '{"string":"é","integer":1,"float":1,"boolean":true}',
            '{"integer":1,"string":"x","float":1,"boolean":true}',
            '{"string":"x","integer":1,"float":1,"boolean":true,"unknown":0}',
            "{\v\"string\":\"x\",\"integer\":1,\"float\":1,\"boolean\":true}",
        ] as $json) {
            static::assertFalse($plan->decode($json), $json);
        }
        $record = \Eventjet\Json\Internal\DirectJsonParser::compile(
            \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::class,
        );
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $record);
        \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::$calls = 0;
        static::assertFalse($record->decode('{"name":"x","id":1,"amount":1,"active":true,"note":null}x'));
        static::assertSame(0, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        /** @psalm-suppress UnusedFunctionCall Deliberately set the global JSON error before decoding. */
        json_decode('invalid');
        static::assertIsObject($record->decode('{"name":"x","id":1,"amount":1,"active":true,"note":null}'));
        static::assertSame(JSON_ERROR_NONE, json_last_error());

        static::assertSame(1, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        $unicode = new class('') {
            public function __construct(
                public string $café,
            ) {}
        };
        $unicodePlan = \Eventjet\Json\Internal\DirectJsonParser::compile($unicode::class);
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $unicodePlan);
        static::assertEquals($unicode, $unicodePlan->decode('{"caf\\u00e9":""}'));
    }

    /** @throws \ReflectionException|\JsonException|Exception|UnknownClassOrInterfaceException */
    public function testDirectListsValidateEveryRecordBeforeCallingConstructors(): void
    {
        $target = new class([]) {
            /** @param list<\Eventjet\Json\Test\Unit\Fixtures\DirectRecord> $records */
            public function __construct(
                public array $records,
            ) {}
        };
        $plan = \Eventjet\Json\Internal\DirectJsonParser::compile($target::class);
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectListPlan::class, $plan);
        $first = '{"name":"one","id":-1,"amount":1.25,"active":true,"note":null}';
        $second = '{"name":"two","id":2,"amount":2e1,"active":false,"note":"ok"}';
        $json = '{"records":[' . $first . ',' . $second . ']}';
        \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::$calls = 0;
        /** @psalm-suppress UnusedFunctionCall Deliberately set the global JSON error before decoding. */
        json_decode('invalid');
        $actual = $plan->decode($json);
        static::assertSame(JSON_ERROR_NONE, json_last_error());
        static::assertEquals(\Eventjet\Json\Json::decode($json, $target::class), $actual);
        static::assertSame(4, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        static::assertEquals($target, $plan->decode('{"records":[]}'));
        static::assertEquals($target, $plan->decode("{\"records\":[ \n\t ]}"));
        static::assertIsObject($plan->decode(' { "records" : [ ' . $first . " ,\n" . $second . ' ] } '));
        static::assertIsObject($plan->decode('{"records":[' . $first . " \n, " . $second . " \t, " . $first . ']}'));
        $unicode = new class([]) {
            /** @param list<\Eventjet\Json\Test\Unit\Fixtures\DirectRecord> $éléments */
            public function __construct(
                public array $éléments,
            ) {}
        };
        $unicodePlan = \Eventjet\Json\Internal\DirectJsonParser::compile($unicode::class);
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectListPlan::class, $unicodePlan);
        static::assertEquals($unicode, $unicodePlan->decode('{"\\u00e9l\\u00e9ments":[]}'));
        foreach ([
            '{"records":[' . $first . ',' . str_replace('"id":2', replace: '"id":"invalid"', subject: $second) . ']}',
            '{"records":[' . $first . ',]}',
            '{"records":[' . $first . ']}trailing',
            '{"records":null}',
            '{"records":{}}',
            '{"records":[' . $first . '],"extra":1}',
        ] as $invalid) {
            \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::$calls = 0;
            static::assertFalse($plan->decode($invalid), $invalid);
            static::assertSame(0, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        }
        $nonEmpty = new class([new \Eventjet\Json\Test\Unit\Fixtures\DirectRecord('', 0, 0.0, false, null)]) {
            /** @param non-empty-list<\Eventjet\Json\Test\Unit\Fixtures\DirectRecord> $records */
            public function __construct(
                public array $records,
            ) {}
        };
        $nonEmptyPlan = \Eventjet\Json\Internal\DirectJsonParser::compile($nonEmpty::class);
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectListPlan::class, $nonEmptyPlan);
        static::assertFalse($nonEmptyPlan->decode('{"records":[]}'));
        static::assertIsObject($nonEmptyPlan->decode('{"records":[' . $first . ']}'));
    }

    /** @throws \ReflectionException|\JsonException|Exception|UnknownClassOrInterfaceException */
    public function testDirectCompilationLeavesUnsupportedSchemasToCompatibilityHydration(): void
    {
        foreach ([
            \Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget::class,
            \Eventjet\Json\Test\Unit\Fixtures\AbstractDirectRecord::class,
            \Eventjet\Json\Test\Unit\Fixtures\AbstractDirectRecords::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\PublicPropertiesWithConstructor::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\MappedReference::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\ConstructorlessPublicProperties::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\MixedField::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\ScalarMapFields::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\CombinedCollectionFields::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty::class,
            \Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields::class,
        ] as $class) {
            $compiled = \Eventjet\Json\Internal\DirectJsonParser::compile($class);
            if ($class === \Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields::class) {
                static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $compiled);
                static::assertEquals(
                    new \Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields(true, false),
                    $compiled->decode('{"true":true,"false":false}'),
                );
                continue;
            }
            static::assertFalse($compiled, $class);
        }
        $nested = new class([]) {
            /** @param list<\Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields> $records */
            public function __construct(
                public array $records,
            ) {}
        };
        static::assertFalse(\Eventjet\Json\Internal\DirectJsonParser::compile($nested::class));
    }

    /** @throws ReflectionException|JsonException|Exception|UnknownClassOrInterfaceException|RuntimeException|\PHPUnit\Framework\Exception */
    public function testDirectPlansAreCompiledOnceAfterSuccessfulNativeHydration(): void
    {
        $target = new class(1) {
            public static RuntimeException|null $failure = null;

            /** @throws RuntimeException */
            public function __construct(
                public int $value,
            ) {
                ++\Eventjet\Json\Test\Unit\Fixtures\DirectRecord::$calls;
                if ($value === 2 && self::$failure !== null) {
                    throw self::$failure;
                }
            }
        };
        $readPlans = self::readDirectPlans(...);
        $class = $target::class;
        static::assertArrayNotHasKey($class, $readPlans());
        \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::$calls = 0;
        static::assertEquals($target, \Eventjet\Json\Json::decode('{"value":1}', $class));
        static::assertSame(1, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        static::assertArrayHasKey($class, $readPlans());
        static::assertTrue(self::readDirectPlan($class));
        $invalid = \Eventjet\Json\Json::decode('{', $class);
        static::assertInstanceOf(DecodeError::class, $invalid);
        static::assertSame(1, $invalid->getCode());
        static::assertTrue(self::readDirectPlan($class));
        static::assertEquals($target, \Eventjet\Json\Json::decode('{"value":1}', $class));
        $compiled = self::readDirectPlan($class);
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $compiled);
        static::assertSame(2, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        $other = clone $target;
        $other->value = 3;
        static::assertEquals($other, \Eventjet\Json\Json::decode('{"value":3}', $class));
        static::assertSame($compiled, self::readDirectPlan($class));
        static::assertEquals($target, \Eventjet\Json\Json::decode('{"ignored":[1,2],"value":1}', $class));
        static::assertSame($compiled, self::readDirectPlan($class));
        $class::$failure = new RuntimeException('constructor failure');
        $error = \Eventjet\Json\Json::decode('{"value":2}', $class);
        static::assertInstanceOf(DecodeError::class, $error);
        static::assertSame($class::$failure, $error->getPrevious());
        static::assertSame(3, $error->getCode());
        static::assertSame(5, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());

        $scalarList = new class([]) {
            /** @param list<int> $values */
            public function __construct(
                public array $values,
            ) {}
        };
        for ($index = 0; $index < 3; ++$index) {
            static::assertEquals($scalarList, \Eventjet\Json\Json::decode('{"values":[]}', $scalarList::class));
        }
        static::assertFalse(self::readDirectPlan($scalarList::class));
        $nestedList = new class([]) {
            /** @param list<list<ScalarFields>> $values */
            public function __construct(
                public array $values,
            ) {}
        };
        static::assertFalse(\Eventjet\Json\Internal\DirectJsonParser::compile($nestedList::class));
        $map = new class([]) {
            /** @param array<string, int> $values */
            public function __construct(
                public array $values,
            ) {}
        };
        static::assertFalse(\Eventjet\Json\Internal\DirectJsonParser::compile($map::class));
        $mapped = new class([]) implements \JsonSerializable {
            use \Eventjet\Json\MappedJsonFields;

            /** @param list<ScalarFields> $values */
            public function __construct(
                #[Field('records')]
                public array $values,
            ) {}
        };
        static::assertFalse(\Eventjet\Json\Internal\DirectJsonParser::compile($mapped::class));
    }

    /**
     * @return array<class-string, \Eventjet\Json\Internal\DirectScalarPlan|\Eventjet\Json\Internal\DirectListPlan|bool>
     * @phpstan-impure
     * @throws ReflectionException
     */
    private static function readDirectPlans(): array
    {
        /**
         * @var array<class-string, \Eventjet\Json\Internal\DirectScalarPlan|\Eventjet\Json\Internal\DirectListPlan|bool> $cache
         * @mago-expect lint:inline-variable-return The annotation types the reflected cache for analyzers.
         */
        $cache = new ReflectionProperty(\Eventjet\Json\Json::class, 'directPlans')->getValue();
        return $cache;
    }

    /**
     * @param class-string $class
     * @phpstan-impure
     * @throws ReflectionException|\PHPUnit\Framework\Exception
     */
    private static function readDirectPlan(string $class): \Eventjet\Json\Internal\DirectScalarPlan|\Eventjet\Json\Internal\DirectListPlan|bool
    {
        $plans = self::readDirectPlans();
        if (!array_key_exists($class, $plans)) {
            static::fail('The decoded class must have a cached eligibility marker or plan.');
        }
        return $plans[$class];
    }
}
