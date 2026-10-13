<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\CollectionCompiler;
use Eventjet\Json\Internal\CollectionUnionCompiler;
use Eventjet\Json\Internal\CollectionUnionNatives;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldNameRules;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\LiteralFieldCompiler;
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
use Eventjet\Json\Internal\PhpDocConstantResolver;
use Eventjet\Json\Internal\PhpDocExtendedTypeParser;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocItemCompiler;
use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeTokens;
use Eventjet\Json\Internal\PhpDocUnionCompiler;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\PublicPropertyValues;
use Eventjet\Json\Internal\RenamedArguments;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\DeferredEnumBacking;
use Eventjet\Json\Test\Unit\Fixtures\DeferredListItem;
use Eventjet\Json\Test\Unit\Fixtures\DeferredValueEnum;
use Eventjet\Json\Test\Unit\Fixtures\JsonObject;
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
use stdClass;
use TypeError;

use function class_exists;
use function enum_exists;
use function spl_autoload_register;
use function spl_autoload_unregister;

#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(ConstructorPlan::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(ConstructorParameter::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(PublicPropertyValues::class)]
#[UsesClass(Field::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[CoversClass(TargetClass::class)]
#[CoversClass(FieldNameRules::class)]
#[UsesClass(CollectionCompiler::class)]
#[UsesClass(CollectionUnionCompiler::class)]
#[UsesClass(CollectionUnionNatives::class)]
#[UsesClass(EnumNode::class)]
#[UsesClass(ListNode::class)]
#[UsesClass(MapNode::class)]
#[UsesClass(NativeUnionCompiler::class)]
#[UsesClass(NullOnlyNode::class)]
#[UsesClass(NullableNode::class)]
#[UsesClass(ObjectNode::class)]
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
#[UsesClass(PublicPropertyTypeValidator::class)]
#[UsesClass(ScalarNode::class)]
#[UsesClass(ScalarItems::class)]
#[UsesClass(TupleNode::class)]
#[UsesClass(TypeCompiler::class)]
#[UsesClass(UncheckedNode::class)]
#[UsesClass(UnionBuilder::class)]
#[CoversClass(UnionNode::class)]
#[UsesClass(ClassDecoder::class)]
#[UsesClass(ClassDecoders::class)]
#[UsesClass(ClassGraphValidator::class)]
#[UsesClass(DecodeErrorKind::class)]
#[UsesClass(InvalidValue::class)]
#[UsesClass(MappedObjectSerializer::class)]
final class AutoloadingTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testScalarAndEnumConstructorsDoNotLoadPropertyOrLiteralCode(): void
    {
        $target = new class('expected') {
            public function __construct(
                public string $value,
            ) {}
        };
        for ($lookup = 0; $lookup < 2; ++$lookup) {
            static::assertEquals($target, ClassDecoders::hydrate($target::class, JsonObject::of([
                'value' => 'expected',
            ])));
            static::assertFalse(class_exists(PublicPropertyValues::class, autoload: false));
            static::assertFalse(class_exists(LiteralFieldCompiler::class, autoload: false));
            static::assertFalse(class_exists(PhpDocLiteral::class, autoload: false));
            static::assertFalse(class_exists(TypeCompiler::class, autoload: false));
            static::assertFalse(class_exists(FieldNameRules::class, autoload: false));
            static::assertFalse(class_exists(RenamedArguments::class, autoload: false));
        }
        $status = new class(StringBackedStatus::Ready) {
            public function __construct(
                public StringBackedStatus $status,
            ) {}
        };
        static::assertEquals($status, ClassDecoders::hydrate($status::class, JsonObject::of(['status' => 'ready'])));
        static::assertFalse(class_exists(LiteralFieldCompiler::class, autoload: false));
        $ordinary = new class(new EmptyObject()) {
            /** @param \Eventjet\Json\Test\Acceptance\Fixtures\EmptyObject $object */
            public function __construct(
                public EmptyObject $object,
            ) {}
        };
        $ordinaryInput = new stdClass();
        $ordinaryInput->object = new stdClass();
        static::assertEquals($ordinary, ClassDecoders::hydrate($ordinary::class, $ordinaryInput));
        static::assertFalse(class_exists(PhpDocClassNameResolver::class, autoload: false));
        $collection = new class([42]) {
            /** @param list<int> $values The name has a native scalar type. */
            public function __construct(
                public array $values,
                public string $name = '',
            ) {}
        };
        $collectionInput = new stdClass();
        $collectionInput->values = [42];
        static::assertEquals($collection, ClassDecoders::hydrate($collection::class, $collectionInput));
        static::assertFalse(class_exists(PhpDocConstantResolver::class, autoload: false));
        static::assertFalse(class_exists(LiteralFieldCompiler::class, autoload: false));
        static::assertFalse(class_exists(PhpDocLiteral::class, autoload: false));
        static::assertFalse(class_exists(PhpDocExtendedTypeParser::class, autoload: false));
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws TypeError
     */
    public function testEmptyObjectListsDoNotAutoloadItemClasses(): void
    {
        static::assertFalse(class_exists(DeferredListItem::class, autoload: false));
        $requests = new class {
            public int $count = 0;
        };
        $autoload = static function (string $name) use ($requests): void {
            if ($name === DeferredListItem::class) {
                ++$requests->count;
            }
        };
        spl_autoload_register($autoload, prepend: true);
        try {
            $items = new ListNode(new ObjectNode(DeferredListItem::class, false), false);
            static::assertSame([], $items->decode([], 'items'));
            static::assertSame(0, $requests->count);
            static::assertFalse(class_exists(DeferredListItem::class, autoload: false));
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

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
            static::assertEquals($target, ClassDecoders::hydrate($class->getName(), JsonObject::of($values)));
            foreach ($values as $name => $value) {
                $parameter = new ReflectionParameter([$class->getName(), '__construct'], $name);
                $type = $parameter->getType();
                static::assertInstanceOf(ReflectionNamedType::class, $type);
                static::assertNotNull(new ConstructorParameter($parameter, $class)->nativeCheck());
                $compiler = new TypeCompiler();
                $property = $class->getProperty($name);
                foreach ([$parameter, $property] as $field) {
                    $node = $compiler->field($field, $type);
                    static::assertNotInstanceOf(DecodeError::class, $node);
                    static::assertSame($value, $node->decode($value, $name));
                }
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
     * @throws JsonException
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
            $enum = EnumNode::of(DeferredValueEnum::class);
            static::assertNotNull($enum);
            static::assertInstanceOf(DecodeError::class, $enum->decode(1, 'value'));
            static::assertNotContains(DeferredEnumBacking::class, $requests->names);
            static::assertSame(DeferredValueEnum::Ready, $enum->decode('ready', 'value'));
            static::assertContains(DeferredEnumBacking::class, $requests->names);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOrdinaryScalarPhpDocDoesNotLoadLiteralResolution(): void
    {
        $object = new class {
            /** @var positive-int */
            public int $value = 42;
            public int $undocumented = 42;
        };
        foreach (['undocumented', 'value'] as $name) {
            $property = new ReflectionProperty($object, $name);
            static::assertInstanceOf(ScalarNode::class, PublicPropertyTypeValidator::validate($property));
        }
        static::assertFalse(class_exists(TypeCompiler::class, autoload: false));
        static::assertFalse(class_exists(LiteralFieldCompiler::class, autoload: false));
        static::assertFalse(class_exists(PhpDocLiteral::class, autoload: false));
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
            static::assertSame($expected, TargetClass::fieldNames($class));
            static::assertSame($expected, TargetClass::fieldNames($class));
            static::assertSame(1, $class->propertyLookups);
            static::assertSame(1, $class->parentLookups);
        }
    }
}
