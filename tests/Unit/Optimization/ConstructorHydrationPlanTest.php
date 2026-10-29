<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassField;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\CollectionDeclarationError;
use Eventjet\Json\Internal\EnumRoots;
use Eventjet\Json\Internal\Error\DecodeErrors;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldNameRules;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NamedTypeCompiler;
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
use Eventjet\Json\Internal\NodeCompiler;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocNarrowing;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeResolver;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\JsonObject;
use Eventjet\Json\Test\Unit\Fixtures\ThrownDecodeError;
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
use ReflectionProperty;
use stdClass;

use function class_exists;

#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(DecodeErrors::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(TargetClass::class)]
#[CoversClass(FieldNameRules::class)]
#[CoversClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(NamedTypeCompiler::class)]
#[UsesClass(NodeCompiler::class)]
#[UsesClass(PhpDocNarrowing::class)]
#[UsesClass(PhpDocTypeResolver::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
#[UsesClass(PhpDocClassNameResolver::class)]
#[UsesClass(PhpDocFieldType::class)]
#[UsesClass(CollectionDeclarationError::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(PhpDocTupleEntry::class)]
#[UsesClass(PhpDocType::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLexer::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSyntaxError::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocCallableParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenState::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeTree::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNestedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocAtomicTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNamedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocHtmlDetector::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocGenericParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantNameParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocCallableReturnParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocArrayShapeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocShapeItemParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagValues::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocOtherTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocMethodTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocMethodParameterTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTextParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDescriptionParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineArrayParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocChildKind::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTag::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[UsesClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[UsesClass(UnionNode::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassField::class)]
#[CoversClass(ClassDecoders::class)]
#[CoversClass(EnumRoots::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(InvalidValue::class)]
final class ConstructorHydrationPlanTest extends TestCase
{
    /**
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws UnknownClassOrInterfaceException
     * @throws DecodeError
     */
    public function testUnionsPreserveJsonValueTypes(): void
    {
        $string = EnumNode::of(StringBackedStatus::class);
        $int = EnumNode::of(IntBackedStatus::class);
        $union = UnionNode::of('value', 'StringBackedStatus|IntBackedStatus|bool|null', [
            $string,
            $int,
            new ScalarNode('bool', false),
            new NullOnlyNode('null'),
        ]);
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
            static::assertSame(
                DecodeErrorKind::InvalidValue,
                ThrownDecodeError::of(
                    /** @throws DecodeError|JsonException */ static fn(): mixed => $union->decode($value, 'value'),
                )->kind,
            );
        }

        $numbers = UnionNode::of('value', 'int|float', [
            new ScalarNode('int', false),
            new ScalarNode('float', false),
        ]);
        static::assertSame(1, $numbers->decode(1, 'value'));
        static::assertSame(1.5, $numbers->decode(1.5, 'value'));
        $floats = UnionNode::of('value', 'float|string', [
            new ScalarNode('float', false),
            new ScalarNode('string', false),
        ]);
        static::assertSame(1.0, $floats->decode(1, 'value'));
    }

    /**
     * A value that already has its field's scalar type skips the field's node, so no error path is built for it.
     * FieldPath, which builds error paths, stays unloaded until a value needs its node.
     *
     * @throws Exception
     * @throws JsonException
     * @throws ReflectionException
     * @throws DecodeError
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValuesOfTheDeclaredScalarTypeSkipTheirNodes(): void
    {
        $target = new class {
            public function __construct(
                public bool $boolean = false,
                public float $float = 0.5,
                public int $integer = 0,
                public string $string = '',
                public string|null $nullable = '',
            ) {}
        };
        $class = new ReflectionClass($target);
        $values = ['boolean' => true, 'float' => 1.5, 'integer' => 1, 'string' => 'value', 'nullable' => null];
        static::assertEquals(
            $class->newInstanceArgs($values),
            ClassDecoders::hydrate($class->getName(), JsonObject::of($values), 'nested'),
        );
        static::assertFalse(class_exists(FieldPath::class, autoload: false));
        static::assertEquals(
            $class->newInstanceArgs(['float' => 2.0]),
            ClassDecoders::hydrate($class->getName(), JsonObject::of(['float' => 2]), 'nested'),
        );
        static::assertTrue(class_exists(FieldPath::class, autoload: false));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     * @throws DecodeError
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
            InvalidValue::typeMismatch('nested.value', 'int', null)->whileCreating($class),
            ThrownDecodeError::of(
                /** @throws DecodeError */ static fn(): object => ClassDecoders::hydrate($class, $input, 'nested'),
            ),
        );
        static::assertSame($decoder, self::cached($class));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws DecodeError
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
            InvalidValue::typeMismatch('nested.label', 'string', false)->whileCreating($class),
            ThrownDecodeError::of(
                /** @throws DecodeError */ static fn(): object => ClassDecoders::hydrate($class, $input, 'nested'),
            ),
        );
        static::assertSame($decoder, self::cached($class));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     * @throws DecodeError
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
        $error = ThrownDecodeError::of(
            /** @throws DecodeError */ static fn(): object => ClassDecoders::hydrate($class, $input, 'nested'),
        );
        static::assertStringContainsString('Field nested.value must be of type', $error->getMessage());
        static::assertSame($decoder, self::cached($class));
    }

    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws DecodeError
     */
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
                InvalidValue::typeMismatch('nested.$ref', 'string|null', 42)->whileCreating($class),
                ThrownDecodeError::of(
                    /** @throws DecodeError */ static fn(): object => ClassDecoders::hydrate($class, $invalid, 'nested'),
                ),
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
