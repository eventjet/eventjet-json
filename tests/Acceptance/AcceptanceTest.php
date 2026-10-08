<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ArrayJsonType;
use Eventjet\Json\Internal\BackedEnumCaseFinder;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassJsonType;
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
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorParameters;
use Eventjet\Json\Internal\ConstructorValidationPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\EnumFieldTypes;
use Eventjet\Json\Internal\EnumUnionValidator;
use Eventjet\Json\Internal\FieldCollectionUnionMemberResolver;
use Eventjet\Json\Internal\FieldCollectionUnionResolver;
use Eventjet\Json\Internal\FieldCollectionUnionType;
use Eventjet\Json\Internal\FieldCollectionUnionValidator;
use Eventjet\Json\Internal\FieldCollectionUnionValueConverter;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\ListInputNormalizer;
use Eventjet\Json\Internal\ListType;
use Eventjet\Json\Internal\ListValueConverter;
use Eventjet\Json\Internal\MapDecodeError;
use Eventjet\Json\Internal\MapInputNormalizer;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Internal\MapType;
use Eventjet\Json\Internal\MapTypeResolver;
use Eventjet\Json\Internal\MapValueConverter;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NamedFieldValueConverter;
use Eventjet\Json\Internal\NestedCollectionType;
use Eventjet\Json\Internal\NestedCollectionTypeResolver;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\ObjectValueConverter;
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
use Eventjet\Json\Internal\PublicPropertyHydrator;
use Eventjet\Json\Internal\PublicPropertyNamedValueConverter;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\PublicPropertyUnionValueConverter;
use Eventjet\Json\Internal\PublicPropertyValueConverter;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\TupleType;
use Eventjet\Json\Internal\TupleTypeResolver;
use Eventjet\Json\Internal\TupleValueConverter;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Cases\ConstructorDefaultCases;
use Eventjet\Json\Test\Acceptance\Cases\DecodeErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\EmptyShapeRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\JsonFormattingRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\ObjectRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\ParserSyntaxCases;
use Eventjet\Json\Test\Acceptance\Cases\RootCollectionRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\UnknownFieldCases;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(ConstructorParameter::class)]
#[CoversClass(ConstructorParameters::class)]
#[CoversClass(Json::class)]
#[CoversClass(JsonType::class)]
#[CoversClass(ArrayJsonType::class)]
#[CoversClass(ClassJsonType::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(BackedEnumCaseFinder::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[CoversClass(EnumFieldTypes::class)]
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
#[CoversClass(FieldCollectionUnionType::class)]
#[CoversClass(FieldCollectionUnionResolver::class)]
#[CoversClass(FieldCollectionUnionValueConverter::class)]
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
#[CoversClass(PhpDocTypeParser::class)]
#[CoversClass(PhpDocTokenStream::class)]
#[CoversClass(PhpDocNamespaceDeclaration::class)]
#[CoversClass(ListInputNormalizer::class)]
#[CoversClass(ListValueConverter::class)]
#[CoversClass(MapDecodeError::class)]
#[CoversClass(MapInputNormalizer::class)]
#[CoversClass(MapJsonType::class)]
#[CoversClass(MapValueConverter::class)]
#[CoversClass(MetadataCache::class)]
#[CoversClass(NamedFieldValueConverter::class)]
#[CoversClass(NestedCollectionType::class)]
#[CoversClass(NestedCollectionTypeResolver::class)]
#[CoversClass(ObjectTypeValidator::class)]
#[CoversClass(ConstructorValidationPlan::class)]
#[CoversClass(ConstructorValueValidator::class)]
#[CoversClass(ObjectValueConverter::class)]
#[CoversClass(ObjectHydrator::class)]
#[CoversClass(PublicPropertyHydrator::class)]
#[CoversClass(\Eventjet\Json\Internal\PublicProperties::class)]
#[CoversClass(PublicPropertyNamedValueConverter::class)]
#[CoversClass(PublicPropertyTypeValidator::class)]
#[CoversClass(PublicPropertyUnionValueConverter::class)]
#[CoversClass(PublicPropertyValueConverter::class)]
#[CoversClass(RootTypeValidator::class)]
#[CoversClass(ValueTypeMatcher::class)]
final class AcceptanceTest extends TestCase
{
    #[DataProviderExternal(ParserSyntaxCases::class, 'types')]
    public function testPhpDocParserReturnsExpectedSyntaxTree(string $source, PhpDocType|null $expected): void
    {
        static::assertEquals($expected, PhpDocTypeParser::parse($source));
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

    /** @throws JsonException */
    #[DataProviderExternal(JsonFormattingRoundTripCases::class, 'objects')]
    #[DataProviderExternal(EmptyShapeRoundTripCases::class, 'objects')]
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
    #[DataProviderExternal(\Eventjet\Json\Test\Acceptance\Cases\SupportedDocumentRoundTripCases::class, 'objects')]
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
    #[DataProviderExternal(\Eventjet\Json\Test\Acceptance\Cases\NumericObjectKeyCases::class, 'mismatches')]
    public function testNumericObjectKeysCannotBypassScalarValidation(string $json, string $class): void
    {
        $decoded = Json::decode($json, $class);
        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame(3, $decoded->getCode());
    }
}
