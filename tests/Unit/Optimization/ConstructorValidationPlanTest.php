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
#[UsesClass(\Eventjet\Json\Internal\ClassUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\EnumUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldCollectionUnionResolver::class)]
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
#[CoversClass(\Eventjet\Json\Internal\DirectJsonParser::class)]
#[CoversClass(\Eventjet\Json\Internal\DirectScalarPlan::class)]
#[CoversClass(\Eventjet\Json\Internal\DirectListPlan::class)]
#[CoversClass(\Eventjet\Json\Json::class)]
#[UsesClass(\Eventjet\Json\Internal\NativeJsonDecoder::class)]
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
        static::assertEquals(
            \Eventjet\Json\Internal\NativeJsonDecoder::decode($json, $target::class),
            $plan->decode($json),
        );
        static::assertSame(4, \Eventjet\Json\Test\Unit\Fixtures\DirectRecord::constructionCount());
        static::assertSame(JSON_ERROR_NONE, json_last_error());
        static::assertEquals($target, $plan->decode('{"records":[]}'));
        static::assertIsObject($plan->decode(' { "records" : [ ' . $first . " ,\n" . $second . ' ] } '));
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

    /** @throws ReflectionException|JsonException|Exception|UnknownClassOrInterfaceException */
    public function testDirectPlansAreCompiledOnceAfterSuccessfulNativeHydration(): void
    {
        $target = new class(1) {
            public static RuntimeException|null $failure = null;

            public function __construct(public int $value)
            {
                if ($value === 2 && self::$failure !== null) {
                    throw self::$failure;
                }
            }
        };
        $readPlans = self::readDirectPlans(...);
        $class = $target::class;
        static::assertArrayNotHasKey($class, $readPlans());
        static::assertEquals($target, \Eventjet\Json\Json::decode('{"value":1}', $class));
        static::assertArrayHasKey($class, $readPlans());
        static::assertNull($readPlans()[$class]);
        $invalid = \Eventjet\Json\Json::decode('{', $class);
        static::assertInstanceOf(DecodeError::class, $invalid);
        static::assertSame(1, $invalid->getCode());
        static::assertNull($readPlans()[$class]);
        static::assertEquals($target, \Eventjet\Json\Json::decode('{"value":1}', $class));
        $compiled = $readPlans()[$class];
        static::assertInstanceOf(\Eventjet\Json\Internal\DirectScalarPlan::class, $compiled);
        $other = clone $target;
        $other->value = 3;
        static::assertEquals($other, \Eventjet\Json\Json::decode('{"value":3}', $class));
        static::assertSame($compiled, $readPlans()[$class]);
        static::assertEquals($target, \Eventjet\Json\Json::decode('{"ignored":[1,2],"value":1}', $class));
        static::assertSame($compiled, $readPlans()[$class]);
        $class::$failure = new RuntimeException('constructor failure');
        $error = \Eventjet\Json\Json::decode('{"value":2}', $class);
        static::assertInstanceOf(DecodeError::class, $error);
        static::assertSame($class::$failure, $error->getPrevious());
        static::assertSame(3, $error->getCode());

        $scalarList = new class([]) {
            /** @param list<int> $values */
            public function __construct(public array $values) {}
        };
        for ($index = 0; $index < 3; ++$index) {
            static::assertEquals($scalarList, \Eventjet\Json\Json::decode('{"values":[]}', $scalarList::class));
        }
        static::assertFalse($readPlans()[$scalarList::class]);
        $nestedList = new class([]) {
            /** @param list<list<ScalarFields>> $values */
            public function __construct(public array $values) {}
        };
        static::assertFalse(\Eventjet\Json\Internal\DirectJsonParser::compile($nestedList::class));
        $map = new class([]) {
            /** @param array<string, int> $values */
            public function __construct(public array $values) {}
        };
        static::assertFalse(\Eventjet\Json\Internal\DirectJsonParser::compile($map::class));
    }

    /**
     * @return array<class-string, \Eventjet\Json\Internal\DirectScalarPlan|\Eventjet\Json\Internal\DirectListPlan|false|null>
     * @phpstan-impure
     * @throws ReflectionException
     */
    private static function readDirectPlans(): array
    {
        /** @var array<class-string, \Eventjet\Json\Internal\DirectScalarPlan|\Eventjet\Json\Internal\DirectListPlan|false|null> $cache */
        $cache = new ReflectionProperty(\Eventjet\Json\Json::class, 'directPlans')->getValue();
        return $cache;
    }
}
