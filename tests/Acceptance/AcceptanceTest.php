<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

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
use Eventjet\Json\Internal\CollectionUnionNatives;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\DecodeErrorKind;
use Eventjet\Json\Internal\FieldNameCollisions;
use Eventjet\Json\Internal\FieldNames;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\LiteralFieldCompiler;
use Eventjet\Json\Internal\MapJsonType;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NativeUnionCompiler;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\LiteralNode;
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
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(ClassGraphValidator::class)]
#[CoversClass(ConstructorParameter::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocExtendedTypeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocExtendedTypeTokens::class)]
#[CoversClass(Json::class)]
#[CoversClass(JsonType::class)]
#[CoversClass(ArrayJsonType::class)]
#[CoversClass(ClassJsonType::class)]
#[CoversClass(MapJsonType::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[CoversClass(PhpDocTupleEntry::class)]
#[CoversClass(FieldPath::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(PhpDocImportScanner::class)]
#[CoversClass(PhpDocImportStatement::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantImportStatement::class)]
#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(PhpDocImports::class)]
#[CoversClass(PhpDocFieldType::class)]
#[CoversClass(PhpDocTypeTokens::class)]
#[CoversClass(PhpDocType::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteral::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralNumber::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantResolver::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantValues::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocStringEscape::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralEnumOverlap::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralNativeType::class)]
#[CoversClass(PhpDocTypeParser::class)]
#[CoversClass(PhpDocTokenStream::class)]
#[CoversClass(PhpDocNamespaceDeclaration::class)]
#[CoversClass(ConstructorPlan::class)]
#[CoversClass(PublicProperties::class)]
#[CoversClass(PublicPropertyTypeValidator::class)]
#[CoversClass(RootTypeValidator::class)]
#[CoversClass(ValueTypeMatcher::class)]
#[CoversClass(TypeCompiler::class)]
#[CoversClass(CollectionCompiler::class)]
#[CoversClass(EnumNode::class)]
#[CoversClass(ListNode::class)]
#[CoversClass(MapNode::class)]
#[CoversClass(NullableNode::class)]
#[CoversClass(NullOnlyNode::class)]
#[CoversClass(ObjectNode::class)]
#[CoversClass(ScalarNode::class)]
#[CoversClass(ScalarItems::class)]
#[CoversClass(TupleNode::class)]
#[CoversClass(UncheckedNode::class)]
#[CoversClass(UnionNode::class)]
#[CoversClass(Field::class)]
#[CoversMethod(MappedJsonFields::class, 'jsonSerialize')]
#[CoversClass(MappedObjectSerializer::class)]
#[CoversClass(FieldNameCollisions::class)]
#[CoversClass(FieldNames::class)]
#[CoversClass(CollectionUnionCompiler::class)]
#[CoversClass(CollectionUnionNatives::class)]
#[CoversClass(DecodeErrorKind::class)]
#[CoversClass(NativeUnionCompiler::class)]
#[CoversClass(PhpDocCollectionCompiler::class)]
#[CoversClass(PhpDocItemCompiler::class)]
#[CoversClass(PhpDocUnionCompiler::class)]
#[CoversClass(UnionBuilder::class)]
#[CoversClass(LiteralFieldCompiler::class)]
#[CoversClass(LiteralNode::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassDecoders::class)]
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
