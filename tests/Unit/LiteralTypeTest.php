<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\CollectionUnionType;
use Eventjet\Json\Internal\ConstructorParameterConverter;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\EnumFieldTypes;
use Eventjet\Json\Internal\ListType;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NestedCollectionTypeResolver;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocConstantResolver;
use Eventjet\Json\Internal\PhpDocConstantValues;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImportKind;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocItemTypeResolver;
use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocLiteralField;
use Eventjet\Json\Internal\PhpDocLiteralFieldCache;
use Eventjet\Json\Internal\PhpDocLiteralFieldValidator;
use Eventjet\Json\Internal\PhpDocLiteralFieldValueConverter;
use Eventjet\Json\Internal\PhpDocLiteralNumber;
use Eventjet\Json\Internal\PhpDocLiteralString;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocStringEscape;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeTokens;
use Eventjet\Json\Internal\PhpDocUnionTypeResolver;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionProperty;

use const INF;
use const NAN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(PhpDocLiteralNumber::class)]
#[CoversClass(PhpDocLiteralString::class)]
#[CoversClass(PhpDocStringEscape::class)]
#[CoversClass(PhpDocConstantValues::class)]
#[CoversClass(ValueTypeMatcher::class)]
#[CoversClass(PhpDocLiteralField::class)]
#[CoversClass(PhpDocLiteralFieldCache::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralFieldMarker::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocParameterMarkerCache::class)]
#[CoversClass(PhpDocLiteralFieldValidator::class)]
#[CoversClass(PhpDocFieldType::class)]
#[UsesClass(PhpDocItemTypeResolver::class)]
#[UsesClass(CollectionUnionType::class)]
#[UsesClass(PhpDocConstantResolver::class)]
#[UsesClass(PhpDocNamespaceDeclaration::class)]
#[UsesClass(PhpDocLiteral::class)]
#[UsesClass(PhpDocUnionTypeResolver::class)]
#[CoversClass(PhpDocType::class)]
#[UsesClass(PhpDocTypeParser::class)]
#[UsesClass(ListType::class)]
#[UsesClass(PhpDocTypeTokens::class)]
#[UsesClass(PhpDocTokenStream::class)]
#[UsesClass(NestedCollectionTypeResolver::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(FieldTypeResolver::class)]
#[CoversClass(FieldTypeValidator::class)]
#[CoversClass(ConstructorParameterConverter::class)]
#[CoversClass(ClassUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassFieldTypeValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\CollectionTypeValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\EnumUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldCollectionUnionResolver::class)]
#[UsesClass(EnumFieldTypes::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldValueConverter::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[UsesClass(\Eventjet\Json\DecodeError::class)]
#[UsesClass(PhpDocLiteralFieldValueConverter::class)]
#[UsesClass(PhpDocClassNameResolver::class)]
#[UsesClass(PhpDocImports::class)]
#[UsesClass(PhpDocImportKind::class)]
#[UsesClass(PhpDocImportScanner::class)]
#[UsesClass(PhpDocImportStatement::class)]
#[UsesClass(MetadataCache::class)]
final class LiteralTypeTest extends TestCase
{
    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    public function testLiteralAndCollectionResolversKeepTheirDeclarationsDistinct(): void
    {
        $object = new class(1) {
            /** @var list<int> */
            public array $items = [];
            /** @var positive-int */
            public int $refined = 1;
            /** @var list<int>|positive-int */
            public array|int $union = 1;

            public function __construct(
                public int $_plain,
            ) {}
        };
        $items = new ReflectionProperty($object, 'items');
        $refined = new ReflectionProperty($object, 'refined');
        $union = new ReflectionProperty($object, 'union');
        $itemsType = $items->getType();
        $unionType = $union->getType();
        static::assertInstanceOf(ReflectionNamedType::class, $itemsType);
        static::assertInstanceOf(\ReflectionUnionType::class, $unionType);

        static::assertFalse(FieldTypeResolver::literalDocComment($items, $itemsType));
        static::assertFalse(FieldTypeResolver::literalDocComment($union, $unionType));
        $refinedType = $refined->getType();
        static::assertInstanceOf(ReflectionNamedType::class, $refinedType);
        static::assertSame($refined->getDocComment(), FieldTypeResolver::literalDocComment($refined, $refinedType));
        static::assertInstanceOf(ListType::class, FieldTypeResolver::resolve($object::class, $items));
        static::assertInstanceOf(ListType::class, FieldTypeResolver::resolve($object::class, $items));

        $parameter = new \ReflectionParameter([$object::class, '__construct'], '_plain');
        $parameterType = $parameter->getType();
        static::assertInstanceOf(ReflectionNamedType::class, $parameterType);
        static::assertFalse(FieldTypeValidator::validateNamedType($object::class, $parameter, $parameterType, false));
        static::assertNull(ConstructorParameterConverter::create($parameter, $parameterType, false, null));

        $invalid = new class(1) {
            public const float NOT_FINITE = INF;

            /** @param int|float|self::NOT_FINITE $value */
            public function __construct(
                public int|float $value,
            ) {}
        };
        $invalidParameter = new \ReflectionParameter([$invalid::class, '__construct'], 'value');
        $invalidType = $invalidParameter->getType();
        static::assertInstanceOf(\ReflectionUnionType::class, $invalidType);
        $error = ClassUnionValidator::resolve(
            $invalid::class,
            $invalidParameter,
            $invalidType,
            $invalidParameter->getDeclaringFunction()->getDocComment(),
        );
        static::assertInstanceOf(\Eventjet\Json\DecodeError::class, $error);
    }

    /** @throws \ReflectionException */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPlainScalarPhpDocDoesNotLoadItemResolution(): void
    {
        $object = new class {
            /** @var float */
            public float $value = 1.0;
        };
        static::assertNull(PhpDocLiteralField::resolve(new ReflectionProperty($object, 'value')));
        static::assertFalse(class_exists(PhpDocItemTypeResolver::class, autoload: false));
    }

    /** @throws \ReflectionException */
    public function testOrdinaryPhpDocDoesNotConstrainScalarValues(): void
    {
        $object = new class {
            /** @var int */
            public int $integer = 42;
            /** @var string|null */
            public string|null $text = null;
            /** @var null|bool */
            public bool|null $flag = null;
            /** @var null */
            public null $nothing = null;
        };
        static::assertNull(PhpDocLiteralField::resolve(new ReflectionProperty($object, 'integer')));
        static::assertNull(PhpDocLiteralField::resolve(new ReflectionProperty($object, 'text')));
        static::assertNull(PhpDocLiteralField::resolve(new ReflectionProperty($object, 'flag')));
        static::assertSame(['null'], PhpDocLiteralField::resolve(new ReflectionProperty($object, 'nothing')));
        static::assertSame(
            PhpDocFieldType::resolve(new ReflectionProperty($object, 'integer')),
            PhpDocFieldType::resolve(new ReflectionProperty($object, 'integer')),
        );
    }

    /** @throws \ReflectionException */
    public function testLiteralMetadataFastPathsAndFieldCacheKeys(): void
    {
        $object = new class(11, 12) {
            public int $value;
            public int $second;
            /** @var 13 */
            public int $property;

            /**
             * @param 11 $value
             * @param 12 $second
             */
            public function __construct(int $value, int $second)
            {
                $this->value = $value;
                $this->second = $second;
                $this->property = 13;
            }

            /** @param 12 $value */
            public function other(int $value): void
            {
                $this->value = $value;
            }
        };
        $constructorParameter = new \ReflectionParameter([$object::class, '__construct'], 'value');
        $secondParameter = new \ReflectionParameter([$object::class, '__construct'], 'second');
        $otherParameter = new \ReflectionParameter([$object::class, 'other'], 'value');

        static::assertSame(['11'], PhpDocLiteralField::resolve($constructorParameter));
        static::assertSame(['12'], PhpDocLiteralField::resolve($secondParameter));
        static::assertSame(['12'], PhpDocLiteralField::resolve($otherParameter));
        static::assertSame(['13'], PhpDocLiteralField::resolve(new ReflectionProperty($object, 'property')));
        static::assertTrue(PhpDocLiteralFieldCache::mayContainLiteral($constructorParameter));
        $plain = new class {
            /**
             * @since 14
             */
            public function plain(int $_plain): void {}
        };
        static::assertFalse(PhpDocLiteralFieldCache::mayContainLiteral(
            new \ReflectionParameter([$plain::class, 'plain'], '_plain'),
        ));
        static::assertFalse(FieldTypeNameResolver::hasLiteralPhpDoc(
            $constructorParameter,
            new ListType('int', nonEmpty: false),
        ));
        $ordinary = new class {
            /** @var \DateTimeImmutable */
            public \DateTimeImmutable $object;
            public int $undocumented;
            /** @var positive-int */
            public int $refined;
            /** @var list<string> */
            public array $nonLiteral;

            public function __construct()
            {
                $this->object = new \DateTimeImmutable();
                $this->undocumented = 0;
                $this->refined = 1;
                $this->nonLiteral = [];
            }
        };
        $objectProperty = new ReflectionProperty($ordinary, 'object');
        $undocumentedProperty = new ReflectionProperty($ordinary, 'undocumented');
        $refinedProperty = new ReflectionProperty($ordinary, 'refined');
        $nonLiteralProperty = new ReflectionProperty($ordinary, 'nonLiteral');

        static::assertFalse(PhpDocLiteralFieldCache::mayContainLiteral($objectProperty));
        static::assertNull(PhpDocLiteralField::resolve($objectProperty));
        static::assertNull(PhpDocLiteralField::resolve($undocumentedProperty));
        static::assertNull(PhpDocLiteralField::resolveUncached($undocumentedProperty));
        static::assertFalse(PhpDocLiteralFieldCache::mayContainLiteral($undocumentedProperty));
        static::assertNull(PhpDocLiteralFieldValidator::validate($ordinary::class, $undocumentedProperty));
        static::assertNull(PhpDocLiteralField::resolve($refinedProperty));
        static::assertNull(PhpDocLiteralField::resolve($nonLiteralProperty));
        static::assertFalse(PhpDocLiteralFieldCache::hasLiteral($undocumentedProperty));
        static::assertFalse(PhpDocLiteralFieldCache::hasLiteral($refinedProperty, $refinedProperty->getDocComment()));
        static::assertFalse(new PhpDocType('|', [new PhpDocType('string'), new PhpDocType('int')])->containsLiteral());

        $ordinaryWithMetadata = new class {
            /** @var int @since 1 */
            public int $value = 1;
        };
        static::assertNull(PhpDocLiteralField::resolveUncached(new ReflectionProperty($ordinaryWithMetadata, 'value')));
        static::assertTrue(new PhpDocType('null')->containsLiteral());
        static::assertTrue(new PhpDocType('true')->containsLiteral());
        static::assertTrue(new PhpDocType('1')->containsLiteral());
        static::assertTrue(new PhpDocType('|', [new PhpDocType('string'), new PhpDocType('1')])->containsLiteral());

        $tagged = new class {
            /**
             * @since 1
             * @var 'a@b'
             */
            public string $value = 'a@b';
        };
        static::assertSame("'a@b'", PhpDocFieldType::resolve(new ReflectionProperty($tagged, 'value'))?->name);

        $enum = new class {
            /** @var StringBackedStatus::Ready */
            public StringBackedStatus $status;

            public function __construct()
            {
                $this->status = StringBackedStatus::Ready;
            }
        };
        $enumProperty = new ReflectionProperty($enum, 'status');
        static::assertTrue(PhpDocLiteralFieldCache::mayContainLiteral($enumProperty));
        static::assertSame([StringBackedStatus::class . '::Ready'], PhpDocLiteralField::resolve($enumProperty));
    }

    public function testIntegerBoundariesAndNotation(): void
    {
        foreach ([
            ['0', 0],
            ['-0', 0],
            ['+42', 42],
            ['-42', -42],
            ['4_2', 42],
            ['0X2A', 42],
            ['0B101010', 42],
            ['0O52', 42],
            ['1E2', 100.0],
            [(string) PHP_INT_MIN, PHP_INT_MIN],
            [(string) PHP_INT_MAX, PHP_INT_MAX],
            ['9223372036854775808', null],
            ['-9223372036854775809', null],
            ['92233720368547758070', null],
            ['-92233720368547758080', null],
            ['0xffffffffffffffffffff', null],
            ['0b11111111111111111111111111111111111111111111111111111111111111111', null],
            ['foo', null],
            ['0xg', null],
            ['1e999', null],
        ] as [$source, $expected]) {
            static::assertSame($expected, PhpDocLiteralNumber::parse($source), $source);
        }
    }

    public function testUnicodeScalarBoundaries(): void
    {
        foreach ([
            ['0',                        "\u{0}"],
            ['d7ff',                     "\u{d7ff}"],
            ['e000',                     "\u{e000}"],
            ['ffff',                     "\u{ffff}"],
            ['10000',                    "\u{10000}"],
            ['103ff',                    "\u{103ff}"],
            ['10400',                    "\u{10400}"],
            ['10ffff',                   "\u{10ffff}"],
            ['d800',                     null],
            ['dfff',                     null],
            ['110000',                   null],
            ['ffffffffffffffffffffffff', null],
        ] as [$code, $expected]) {
            static::assertSame($expected, PhpDocLiteralString::parse('"\\u{' . $code . '}"'), $code);
        }
        static::assertNull(PhpDocLiteralString::parse('not quoted'));
        static::assertNull(PhpDocLiteralString::parse("'mismatched\""));
    }

    public function testConstantValuesKeepScalarTypesAndEscapedStrings(): void
    {
        static::assertSame("'a\\\\b\\'c'", PhpDocConstantValues::name("a\\b'c"));
        static::assertSame("'a\0b'", PhpDocConstantValues::name("a\0b"));
        static::assertSame('null', PhpDocConstantValues::name(null));
        static::assertSame('true', PhpDocConstantValues::name(true));
        static::assertSame('false', PhpDocConstantValues::name(false));
        static::assertSame(
            StringBackedStatus::class . '::Ready',
            PhpDocConstantValues::name(StringBackedStatus::Ready),
        );
        static::assertNull(PhpDocConstantValues::name(INF));
        static::assertNull(PhpDocConstantValues::name(NAN));
        static::assertNull(PhpDocConstantValues::name([]));
    }

    /** @throws \ReflectionException */
    public function testUnsupportedClassConstantCannotBecomeALiteral(): void
    {
        $object = new class {
            public const array VALUES = [42];
        };
        static::assertNull(PhpDocConstantValues::resolve($object::class, 'VALUES'));
    }

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    public function testNativeBooleanLiteralsRejectTheirOppositeValues(): void
    {
        $object = new class {
            public true $yes = true;
            public false $no = false;
        };
        foreach (['yes' => true, 'no' => false] as $field => $expected) {
            $type = new ReflectionProperty($object, $field)->getType();
            static::assertInstanceOf(ReflectionNamedType::class, $type);
            static::assertTrue(ValueTypeMatcher::matches($expected, $type));
            static::assertFalse(ValueTypeMatcher::matches(!$expected, $type));
        }
    }
}
