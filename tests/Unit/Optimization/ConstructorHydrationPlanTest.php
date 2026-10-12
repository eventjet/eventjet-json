<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\CollectionCompiler;
use Eventjet\Json\Internal\CollectionUnionCompiler;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\DecodeErrorKind;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NativeUnionCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\ScalarItems;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
use Eventjet\Json\Internal\Node\UnionNode;
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
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionException;
use ReflectionProperty;
use stdClass;

#[CoversClass(ConstructorPlan::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[CoversClass(ConstructorParameter::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocExtendedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocExtendedTypeTokens::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(FieldNames::class)]
#[CoversClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(FieldNameCollisions::class)]
#[UsesClass(PublicPropertyTypeValidator::class)]
#[UsesClass(CollectionCompiler::class)]
#[UsesClass(CollectionUnionCompiler::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(NativeUnionCompiler::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
#[UsesClass(PhpDocClassNameResolver::class)]
#[UsesClass(PhpDocCollectionCompiler::class)]
#[UsesClass(PhpDocFieldType::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocItemCompiler::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(PhpDocTupleEntry::class)]
#[UsesClass(PhpDocType::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(PhpDocTypeTokens::class)]
#[UsesClass(PhpDocUnionCompiler::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[UsesClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[UsesClass(UnionNode::class)]
#[UsesClass(ArrayJsonType::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassDecoders::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(ClassJsonType::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(MapJsonType::class)]
final class ConstructorHydrationPlanTest extends TestCase
{
    /**
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws UnknownClassOrInterfaceException
     */
    public function testUnionsPreserveJsonValueTypes(): void
    {
        $string = EnumNode::of(ScalarFields::class, StringBackedStatus::class);
        $int = EnumNode::of(ScalarFields::class, IntBackedStatus::class);
        static::assertNotNull($string);
        static::assertNotNull($int);
        $union = UnionNode::of(ScalarFields::class, 'value', 'StringBackedStatus|IntBackedStatus|bool|null', [
            $string,
            $int,
            new ScalarNode(ScalarFields::class, 'bool', false),
            new ScalarNode(ScalarFields::class, 'null', true),
        ]);
        static::assertInstanceOf(UnionNode::class, $union);
        static::assertSame(StringBackedStatus::Ready, $union->decode(StringBackedStatus::Ready->value, 'value'));
        static::assertSame(StringBackedStatus::Pending, $union->decode(StringBackedStatus::Pending->value, 'value'));
        static::assertSame(IntBackedStatus::Ready, $union->decode(IntBackedStatus::Ready->value, 'value'));
        static::assertTrue($union->decode(true, 'value'));
        static::assertNull($union->decode(null, 'value'));
        foreach ([
            (string) IntBackedStatus::Ready->value,
            (float) IntBackedStatus::Ready->value,
            'unknown',
            [],
        ] as $value) {
            static::assertInstanceOf(DecodeError::class, $union->decode($value, 'value'));
        }

        $numbers = UnionNode::of(ScalarFields::class, 'value', 'int|float', [
            new ScalarNode(ScalarFields::class, 'int', false),
            new ScalarNode(ScalarFields::class, 'float', false),
        ]);
        static::assertInstanceOf(UnionNode::class, $numbers);
        static::assertSame(1, $numbers->decode(1, 'value'));
        static::assertSame(1.5, $numbers->decode(1.5, 'value'));
        $floats = UnionNode::of(ScalarFields::class, 'value', 'float|string', [
            new ScalarNode(ScalarFields::class, 'float', false),
            new ScalarNode(ScalarFields::class, 'string', false),
        ]);
        static::assertInstanceOf(UnionNode::class, $floats);
        static::assertSame(1.0, $floats->decode(1, 'value'));
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
        static::assertNull(self::cached($class));
        $input = new stdClass();
        $input->value = 2;
        $target->value = 2;
        static::assertEquals($target, ClassDecoders::hydrate($class, $input));
        $decoder = self::cached($class);
        static::assertNotNull($decoder);
        $input->value = 3;
        $target->value = 3;
        static::assertEquals($target, ClassDecoders::hydrate($class, $input));
        $target->value = 1;
        static::assertEquals($target, ClassDecoders::hydrate($class, new stdClass()));
        $input->value = null;
        static::assertEquals(
            DecodeError::fieldTypeMismatch($class, 'nested.value', 'int', null),
            ClassDecoders::hydrate($class, $input, 'nested'),
        );
        static::assertSame($decoder, self::cached($class));
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
        static::assertEquals($target, ClassDecoders::hydrate($class, $input));
        $decoder = self::cached($class);
        static::assertNotNull($decoder);
        $target->label = 'second';
        $input->label = 'second';
        static::assertEquals($target, ClassDecoders::hydrate($class, $input));
        $input->label = false;
        static::assertEquals(
            DecodeError::fieldTypeMismatch($class, 'nested.label', 'string', false),
            ClassDecoders::hydrate($class, $input, 'nested'),
        );
        static::assertSame($decoder, self::cached($class));
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
        static::assertNull(self::cached($class));
        $input = new stdClass();
        $input->value = StringBackedStatus::Ready->value;
        $target->value = StringBackedStatus::Ready;
        static::assertEquals($target, ClassDecoders::hydrate($class, $input));
        $decoder = self::cached($class);
        static::assertNotNull($decoder);

        foreach ([false, null, StringBackedStatus::Pending] as $value) {
            $target->value = $value;
            $input->value = $value instanceof StringBackedStatus ? $value->value : $value;
            static::assertEquals($target, ClassDecoders::hydrate($class, $input));
        }
        $target->value = null;
        static::assertEquals($target, ClassDecoders::hydrate($class, new stdClass()));
        $input->value = [];
        $error = ClassDecoders::hydrate($class, $input, 'nested');
        static::assertInstanceOf(DecodeError::class, $error);
        static::assertStringContainsString('Field nested.value must be of type', $error->getMessage());
        static::assertSame($decoder, self::cached($class));
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
            static::assertEquals(new MappedDefaults(ref: 'next', count: 9), ClassDecoders::hydrate($class, $input));
            static::assertNotNull(self::cached($class));
            static::assertEquals(new MappedDefaults(), ClassDecoders::hydrate($class, new stdClass()));
            static::assertEquals(
                DecodeError::fieldTypeMismatch($class, 'nested.$ref', 'string|null', 42),
                ClassDecoders::hydrate($class, $invalid, 'nested'),
            );
        }
    }

    /**
     * @param class-string $class
     * @return ClassDecoder|null
     * @throws ReflectionException
     */
    private static function cached(string $class): ClassDecoder|null
    {
        /** @var array<class-string, ClassDecoder> $decoders */
        $decoders = new ReflectionProperty(ClassDecoders::class, 'decoders')->getValue();
        return $decoders[$class] ?? null;
    }
}
