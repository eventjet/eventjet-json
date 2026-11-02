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
use Eventjet\Json\Internal\Error\InvalidFieldMapping;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedClass;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
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
use Eventjet\Json\Internal\UnionConflicts;
use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;
use Eventjet\Json\Test\Acceptance\Cases\CollectionNameSource;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\JsonObject;
use Eventjet\Json\Test\Unit\Fixtures\ThrownDecodeError;
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

#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(DecodeErrors::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(MappedObjectSerializer::class)]
#[UsesClass(Field::class)]
#[CoversClass(TargetClass::class)]
#[CoversClass(FieldNameRules::class)]
#[UsesClass(NamedTypeCompiler::class)]
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
#[UsesClass(UnionConflicts::class)]
#[UsesClass(UnionNode::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassField::class)]
#[CoversClass(ClassDecoders::class)]
#[CoversClass(EnumRoots::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(InvalidFieldMapping::class)]
#[UsesClass(InvalidValue::class)]
#[UsesClass(UnsupportedField::class)]
#[UsesClass(UnsupportedClass::class)]
#[UsesClass(UnsupportedFieldClass::class)]
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
                InvalidValue::typeMismatch('before.first', 'int|null', false)->whileCreating($class->getName()),
                self::decode($class, ['first' => false, 'second' => 1], 'before'),
            );
            static::assertEquals($class->newInstanceArgs([]), self::decode($class, [], ''));
            static::assertEquals(
                $class->newInstanceArgs(['first' => null, 'second' => 'valid']),
                self::decode($class, ['first' => null, 'second' => 'valid'], ''),
            );
            static::assertEquals(
                InvalidValue::typeMismatch('after.second', 'string', 1)->whileCreating($class->getName()),
                self::decode($class, ['first' => 1, 'second' => 1], 'after'),
            );
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     * @throws JsonException
     */
    public function testDeclarationErrorsPrecedeValueErrorsInParameterOrder(): void
    {
        $target = new class {
            public function __construct(
                public int $first = 1,
                public mixed $second = null,
            ) {}
        };
        $class = new ReflectionClass($target);
        $declarationError = UnsupportedField::imprecise('second', 'mixed')->whileCreating($class->getName());
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            static::assertEquals($declarationError, self::decode($class, ['first' => false], ''));
            static::assertEquals($declarationError, self::decode($class, ['first' => 1], ''));
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
            UnsupportedField::imprecise('value', 'mixed')->whileCreating($invalidClass->getName()),
            self::decode($invalidClass, [], ''),
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
                InvalidValue::unknownEnumCase('nested.first', [StringBackedStatus::class], 'unknown')->whileCreating(
                    $enumClass->getName(),
                ),
                self::decode($enumClass, ['second' => false, 'first' => 'unknown'], 'nested'),
            );
            static::assertEquals(
                InvalidValue::typeMismatch('nested.second', 'int', false)->whileCreating($enumClass->getName()),
                self::decode($enumClass, ['first' => StringBackedStatus::Ready->value, 'second' => false], 'nested'),
            );
            static::assertEquals(
                $enumClass->newInstanceArgs(['first' => StringBackedStatus::Ready, 'second' => 1]),
                self::decode($enumClass, ['first' => StringBackedStatus::Ready->value, 'second' => 1], ''),
            );
            static::assertInstanceOf(DecodeError::class, self::decode(
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
        $class = CollectionDeclarationFixture::create($dependency, '', 'param');
        $decoder = ClassDecoders::get($class);
        static::assertFalse($decoder->complete);
        static::assertNotSame($decoder, ClassDecoders::get($class));
        static::assertTrue(class_alias(NonBackedStatus::class, $dependency));
        static::assertSame(
            DecodeErrorKind::UnsupportedDeclaration,
            ThrownDecodeError::of(
                /** @throws DecodeError|ReflectionException */ static fn(): object => ClassDecoders::get($class),
            )->kind,
        );
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws DecodeError
     */
    public function testMappedPlansKeepInputNamesAndArgumentsSeparate(): void
    {
        $class = new ReflectionClass(MappedDefaults::class);
        for ($lookup = 0; $lookup < 3; ++$lookup) {
            static::assertEquals(
                $class->newInstanceArgs(['ref' => 'next', 'count' => 9]),
                self::decode($class, ['$ref' => 'next', 'count' => 9], ''),
            );
            static::assertEquals($class->newInstanceArgs(['ref' => null]), self::decode($class, ['$ref' => null], ''));
            static::assertEquals($class->newInstanceArgs([]), self::decode($class, ['ref' => 'ignored'], ''));
            static::assertSame(ClassDecoders::get(MappedDefaults::class), ClassDecoders::get(MappedDefaults::class));
            static::assertEquals(
                InvalidValue::typeMismatch('nested.$ref', 'string|null', 42)->whileCreating($class->getName()),
                self::decode($class, ['$ref' => 42], 'nested'),
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
            $error = self::decode($class, ['0' => 'unknown'], 'nested');
            static::assertInstanceOf(DecodeError::class, $error);
            static::assertStringContainsString('nested.0', $error->getMessage());
            $rootError = self::decode($class, ['0' => 'unknown'], '');
            static::assertInstanceOf(DecodeError::class, $rootError);
            static::assertStringContainsString('Field 0 uses backed enum', $rootError->getMessage());
            static::assertEquals(
                $class->newInstanceArgs(['value' => StringBackedStatus::Ready]),
                self::decode($class, ['0' => StringBackedStatus::Ready->value], ''),
            );
        }
    }

    /**
     * @throws ReflectionException
     * @throws DecodeError
     * @throws JsonException
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

        $namesClass = CollectionNameSource::load(
            'MappedNonPublicNames',
            'use Eventjet\\Json\\Field; final class MappedNonPublicNames implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; private string $internal = "private"; public static string $shared = "static"; #[Field("internal")] public string $value = "public"; #[Field("shared")] public string $other = "other"; }',
        );
        $namesReflection = new ReflectionClass($namesClass);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            static::assertEquals(
                (object) ['internal' => 'public', 'shared' => 'other'],
                MappedObjectSerializer::serialize($namesReflection->newInstance()),
            );
            $decoded = self::decode($namesReflection, ['internal' => 'decoded', 'shared' => 'also decoded'], '');
            static::assertInstanceOf($namesClass, $decoded);
            static::assertEquals(
                (object) ['internal' => 'decoded', 'shared' => 'also decoded'],
                MappedObjectSerializer::serialize($decoded),
            );
        }
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
        $named = InvalidFieldMapping::collision('value', 'ref', 'ref')->whileCreating($class);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            static::assertEquals($named, ThrownDecodeError::of(
                /**
                 * @throws DecodeError
                 */
                static fn(): mixed => TargetClass::validate($reflection),
            ));
            static::assertEquals($named, self::decode($reflection, [], ''));
            static::assertEquals($named, ThrownDecodeError::of(
                /** @throws DecodeError|ReflectionException */ static fn(): object => ClassDecoders::get($class),
            ));
            try {
                MappedObjectSerializer::serialize($reflection->newInstance());
                static::fail('Invalid mappings must not serialize.');
            } catch (DecodeError $caught) {
                static::assertSame($named->getMessage(), $caught->getMessage());
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
                    . ' { private string $unmapped = ""; #[Field("hidden")] private '
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
                $named = InvalidFieldMapping::nonPublicProperty('value')->whileCreating($class);
                for ($attempt = 0; $attempt < 2; ++$attempt) {
                    static::assertEquals($named, ThrownDecodeError::of(
                        /**
                         * @throws DecodeError
                         */
                        static fn(): mixed => TargetClass::validate($reflection),
                    ));
                    static::assertEquals($named, self::decode($reflection, [], ''));
                    static::assertEquals($named, ThrownDecodeError::of(
                        /**
                         * @throws DecodeError
                         * @throws ReflectionException
                         */
                        static fn(): object => ClassDecoders::get($class),
                    ));
                    try {
                        MappedObjectSerializer::serialize($reflection->newInstance());
                        static::fail('Private ancestor mappings must not serialize.');
                    } catch (DecodeError $caught) {
                        static::assertSame($named->getMessage(), $caught->getMessage());
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
            static::assertSame(['value' => 'wire', 'other' => 'inherited'], TargetClass::fieldNames($reflection));
            static::assertEquals(
                (object) ['wire' => 'child', 'inherited' => 'kept', 'old' => 'ordinary'],
                MappedObjectSerializer::serialize($reflection->newInstance()),
            );
        }
    }

    /**
     * Hydrates the class and returns the object, or the error, as Json::decode() does.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, bool|float|int|string|null> $values
     * @throws JsonException
     */
    private static function decode(ReflectionClass $class, array $values, string $path): object
    {
        $object = JsonObject::of($values);
        return ThrownDecodeError::orResult(
            /** @throws DecodeError */ static fn(): object => ClassDecoders::hydrate($class->getName(), $object, $path),
        );
    }
}
