<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\ClassJsonType;
use Eventjet\Json\Internal\ClassTypeDependencies;
use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\CollectionItemValueConverter;
use Eventjet\Json\Internal\CollectionTypeResolver;
use Eventjet\Json\Internal\CollectionTypeValidator;
use Eventjet\Json\Internal\CollectionUnionShapeValidator;
use Eventjet\Json\Internal\CollectionUnionType;
use Eventjet\Json\Internal\CollectionUnionTypeValidator;
use Eventjet\Json\Internal\CollectionUnionValueConverter;
use Eventjet\Json\Internal\CollectionValueConverter;
use Eventjet\Json\Internal\ConcreteClassUnionValueConverter;
use Eventjet\Json\Internal\ConcreteClassValueConverter;
use Eventjet\Json\Internal\ConstructorDecoder;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorParameterConverter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\EnumFieldTypes;
use Eventjet\Json\Internal\EnumUnionValidator;
use Eventjet\Json\Internal\FieldCollectionUnionMemberResolver;
use Eventjet\Json\Internal\FieldCollectionUnionResolver;
use Eventjet\Json\Internal\FieldCollectionUnionValidator;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\FieldValueConverter;
use Eventjet\Json\Internal\FloatListValueConverter;
use Eventjet\Json\Internal\FloatMapValueConverter;
use Eventjet\Json\Internal\ListInputNormalizer;
use Eventjet\Json\Internal\ListType;
use Eventjet\Json\Internal\ListValueConverter;
use Eventjet\Json\Internal\MapDecodeError;
use Eventjet\Json\Internal\MapInputNormalizer;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Internal\MappedConstructorPlan;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\MapType;
use Eventjet\Json\Internal\MapTypeResolver;
use Eventjet\Json\Internal\MapValueConverter;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NestedCollectionType;
use Eventjet\Json\Internal\NestedCollectionTypeResolver;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocItemTypeResolver;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeTokens;
use Eventjet\Json\Internal\PhpDocUnionTypeResolver;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\PublicPropertyHydrator;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ScalarListValueConverter;
use Eventjet\Json\Internal\ScalarMapValueConverter;
use Eventjet\Json\Internal\TupleType;
use Eventjet\Json\Internal\TupleTypeResolver;
use Eventjet\Json\Internal\TupleValueConverter;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\Test\Acceptance\Cases\ConstructorDefaultCases;
use Eventjet\Json\Test\Acceptance\Cases\DecodeErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\EmptyShapeRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\JsonFormattingRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\NumericObjectKeyCases;
use Eventjet\Json\Test\Acceptance\Cases\ObjectRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\ParserSyntaxCases;
use Eventjet\Json\Test\Acceptance\Cases\RootCollectionRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\SupportedDocumentRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\TypeValidationCases;
use Eventjet\Json\Test\Acceptance\Cases\UnknownFieldCases;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(ClassGraphValidator::class)]
#[CoversClass(ClassTypeDependencies::class)]
#[CoversClass(ConstructorParameter::class)]
#[CoversClass(ConstructorParameterConverter::class)]
#[CoversClass(Json::class)]
#[CoversClass(JsonType::class)]
#[CoversClass(ArrayJsonType::class)]
#[CoversClass(ClassJsonType::class)]
#[CoversClass(MapJsonType::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[CoversClass(EnumFieldTypes::class)]
#[CoversClass(\Eventjet\Json\Internal\EnumUnionLookup::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[CoversClass(CollectionUnionValueConverter::class)]
#[CoversClass(CollectionUnionShapeValidator::class)]
#[CoversClass(CollectionUnionTypeValidator::class)]
#[CoversClass(PhpDocUnionTypeResolver::class)]
#[CoversClass(CollectionUnionType::class)]
#[CoversClass(ClassUnionValidator::class)]
#[CoversClass(CollectionTypeResolver::class)]
#[CoversClass(CollectionTypeValidator::class)]
#[CoversClass(PhpDocTupleEntry::class)]
#[CoversClass(TupleType::class)]
#[CoversClass(MapType::class)]
#[CoversClass(MapTypeResolver::class)]
#[CoversClass(ListType::class)]
#[CoversClass(CollectionValueConverter::class)]
#[CoversClass(CollectionItemValueConverter::class)]
#[CoversClass(TupleValueConverter::class)]
#[CoversClass(TupleTypeResolver::class)]
#[CoversClass(ConcreteClassUnionValueConverter::class)]
#[CoversClass(ConcreteClassValueConverter::class)]
#[CoversClass(EnumUnionValidator::class)]
#[CoversClass(FieldTypeValidator::class)]
#[CoversClass(FieldCollectionUnionMemberResolver::class)]
#[CoversClass(FieldCollectionUnionValidator::class)]
#[CoversClass(FieldCollectionUnionResolver::class)]
#[CoversClass(FieldPath::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(FieldTypeResolver::class)]
#[CoversClass(PhpDocImportScanner::class)]
#[CoversClass(PhpDocImportStatement::class)]
#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(PhpDocImports::class)]
#[CoversClass(PhpDocFieldType::class)]
#[CoversClass(PhpDocTypeTokens::class)]
#[CoversClass(PhpDocItemTypeResolver::class)]
#[CoversClass(PhpDocType::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteral::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralNumber::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantResolver::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantValues::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralField::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralFieldValueConverter::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralFieldCache::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralValueConverter::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocStringEscape::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralEnumOverlap::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralFieldValidator::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralScalarConverter::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralNativeType::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocImportKind::class)]
#[CoversClass(PhpDocTypeParser::class)]
#[CoversClass(PhpDocTokenStream::class)]
#[CoversClass(PhpDocNamespaceDeclaration::class)]
#[CoversClass(ListInputNormalizer::class)]
#[CoversClass(ListValueConverter::class)]
#[CoversClass(ScalarListValueConverter::class)]
#[CoversClass(FloatListValueConverter::class)]
#[CoversClass(MapDecodeError::class)]
#[CoversClass(MapInputNormalizer::class)]
#[CoversClass(MapValueConverter::class)]
#[CoversClass(ScalarMapValueConverter::class)]
#[CoversClass(FloatMapValueConverter::class)]
#[CoversClass(MetadataCache::class)]
#[CoversClass(NestedCollectionType::class)]
#[CoversClass(NestedCollectionTypeResolver::class)]
#[CoversClass(FieldValueConverter::class)]
#[CoversClass(ConstructorDecoder::class)]
#[CoversClass(ConstructorPlan::class)]
#[CoversClass(ConstructorValueValidator::class)]
#[CoversClass(ObjectHydrator::class)]
#[CoversClass(PublicPropertyHydrator::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(PublicPropertyTypeValidator::class)]
#[CoversClass(RootTypeValidator::class)]
#[CoversClass(ValueTypeMatcher::class)]
#[CoversClass(Field::class)]
#[CoversMethod(MappedJsonFields::class, 'jsonSerialize')]
#[CoversClass(MappedObjectSerializer::class)]
#[CoversClass(MappedConstructorPlan::class)]
#[CoversClass(FieldNameCollisions::class)]
#[CoversClass(FieldNames::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralFieldMarker::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocParameterMarkerCache::class)]
final class AcceptanceTest extends TestCase
{
    #[DataProviderExternal(ParserSyntaxCases::class, 'types')]
    public function testPhpDocParserReturnsExpectedSyntaxTree(string $source, PhpDocType|null $expected): void
    {
        static::assertEquals($expected, PhpDocTypeParser::parse($source));
    }

    /** @param class-string|JsonType<mixed> $type */
    #[DataProviderExternal(TypeValidationCases::class, 'declarations')]
    public function testTypeDeclarationsCanBeValidatedWithoutValues(
        string|JsonType $type,
        string|null $expectedError = null,
    ): void {
        $error = Json::validateType($type);
        static::assertSame($expectedError, $error?->getMessage());
    }

    /** @throws JsonException */
    #[DataProviderExternal(ObjectRoundTripCases::class, 'objects')]
    public function testDecodeIsTheExactInverseOfJsonEncode(object $original): void
    {
        $json = json_encode($original, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $original::class);

        static::assertEquals($original, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<mixed>|object $original
     * @param callable(): JsonType<list<mixed>|object> $createType
     * @throws JsonException
     */
    #[DataProviderExternal(RootCollectionRoundTripCases::class, 'objects')]
    public function testRootCollectionRoundTrips(array|object $original, callable $createType): void
    {
        $json = json_encode($original, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $createType());

        static::assertEquals($original, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{object, string}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function jsonDocuments(): iterable
    {
        yield from JsonFormattingRoundTripCases::objects();
        yield from EmptyShapeRoundTripCases::objects();
    }

    /** @throws JsonException */
    #[DataProvider('jsonDocuments')]
    public function testJsonDocumentDecodesWithoutChangingItsMeaning(object $expected, string $json): void
    {
        $decoded = Json::decode($json, $expected::class);

        static::assertEquals($expected, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    /**
     * @param class-string $class
     * @param callable(object): bool $isFullyHydrated
     * @throws JsonException
     */
    #[DataProviderExternal(SupportedDocumentRoundTripCases::class, 'objects')]
    public function testSupportedDocumentIsFullyHydratedAndRoundTrips(
        string $json,
        string $class,
        callable $isFullyHydrated,
    ): void {
        $decoded = Json::decode($json, $class);

        static::assertFalse($decoded instanceof DecodeError);
        static::assertTrue($isFullyHydrated($decoded));
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    #[DataProviderExternal(ConstructorDefaultCases::class, 'objects')]
    #[DataProviderExternal(UnknownFieldCases::class, 'objects')]
    public function testDecodeReturnsExpectedObject(string $json, object $expected): void
    {
        $decoded = Json::decode($json, $expected::class);
        static::assertEquals($expected, $decoded);
    }

    /** @param class-string|JsonType<list<mixed>|object> $class */
    #[DataProviderExternal(DecodeErrorCases::class, 'errors')]
    public function testDecodeReturnsErrorsAsValues(
        string $json,
        string|JsonType $class,
        string $message,
        int $code,
        Throwable|null $previous = null,
    ): void {
        // Separate calls preserve each target's generic type during static analysis.
        $decoded = is_string($class) ? Json::decode($json, $class) : Json::decode($json, $class);
        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame($message, $decoded->getMessage());
        static::assertSame($code, $decoded->getCode());
        if ($previous !== null) {
            static::assertSame($previous, $decoded->getPrevious());
        }
    }

    /** @param class-string $class */
    #[DataProviderExternal(NumericObjectKeyCases::class, 'mismatches')]
    public function testNumericObjectKeysCannotBypassScalarValidation(string $json, string $class): void
    {
        $decoded = Json::decode($json, $class);
        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame(3, $decoded->getCode());
    }
}
