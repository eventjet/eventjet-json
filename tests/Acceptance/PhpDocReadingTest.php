<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Internal\CollectionDeclarationError;
use Eventjet\Json\Internal\NamedTypeCompiler;
use Eventjet\Json\Internal\NodeCompiler;
use Eventjet\Json\Internal\PhpDocNarrowing;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeResolver;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Cases\ParserSyntaxCases;
use Eventjet\Json\Test\Acceptance\Cases\PhpDocDocumentCases;
use Eventjet\Json\Test\Acceptance\Cases\PhpDocReadingCases;
use Eventjet\Json\Test\Acceptance\Cases\PhpDocTagCases;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Doc comments are read like PHPStan reads them. These cases cover the PHPDoc lexer, parsers, and tag readers, and only
 * use the decoder, so that mutation testing runs them only for the code that reads doc comments.
 */
#[CoversClass(\Eventjet\Json\Internal\PhpDocTypeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLexer::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTokens::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSyntaxError::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocCallableParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTokenState::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTypeTree::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocNestedTypeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocAtomicTypeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocNamedTypeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocHtmlDetector::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocGenericParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocConstantNameParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocCallableReturnParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocArrayShapeParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocShapeItemParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTagValues::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocFieldTagValue::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocOtherTagValue::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocMethodTagValue::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocMethodParameterTagValue::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTextParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocDescriptionParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocDoctrineArrayParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTagParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocChildKind::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteralString::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTag::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\AmbiguousUnion::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassDecoder::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassField::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassDecoders::class)]
#[UsesClass(\Eventjet\Json\Internal\EnumRoots::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassGraphValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\ConstructorFailure::class)]
#[UsesClass(\Eventjet\Json\DecodeError::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\DecodeErrors::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\InvalidInput::class)]
#[UsesClass(\Eventjet\Json\DecodeErrorKind::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\EnumNode::class)]
#[UsesClass(\Eventjet\Json\Field::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldNameRules::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldPath::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldTypeNameResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\InvalidFieldMapping::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\InvalidValue::class)]
#[UsesClass(\Eventjet\Json\Json::class)]
#[UsesClass(\Eventjet\Json\JsonType::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ListNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\LiteralNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\MapNode::class)]
#[UsesClass(\Eventjet\Json\Internal\MappedObjectSerializer::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\NullOnlyNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\NullableNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ObjectNode::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocClassNameResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantImportStatement::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocConstantValues::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImportScanner::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImportStatement::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImports::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocLiteral::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocIntegerRange::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralEnumOverlap::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralNativeType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralUnionValidator::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNamespaceDeclaration::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenStream::class)]
#[CoversClass(\Eventjet\Json\Internal\PhpDocTupleEntry::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocType::class)]
#[UsesClass(\Eventjet\Json\Internal\RootDescriptor::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ScalarItems::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ScalarNode::class)]
#[UsesClass(\Eventjet\Json\Internal\TargetClass::class)]
#[UsesClass(\Eventjet\Json\Internal\TargetPosition::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\TupleNode::class)]
#[UsesClass(\Eventjet\Json\Internal\TypeCompiler::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\UncheckedNode::class)]
#[UsesClass(\Eventjet\Json\Internal\UnionBuilder::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\UnionNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\UnsupportedCollection::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\UnsupportedField::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\UnsupportedFieldClass::class)]
#[UsesClass(\Eventjet\Json\Internal\Error\UnsupportedTarget::class)]
#[UsesClass(CollectionDeclarationError::class)]
#[UsesClass(NamedTypeCompiler::class)]
#[UsesClass(NodeCompiler::class)]
#[UsesClass(PhpDocTypeResolver::class)]
#[UsesClass(PhpDocNarrowing::class)]
final class PhpDocReadingTest extends TestCase
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield from PhpDocReadingCases::objects();
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield from PhpDocReadingCases::errors();
        yield from PhpDocTagCases::errors();
        yield from PhpDocDocumentCases::errors();
    }

    #[DataProviderExternal(ParserSyntaxCases::class, 'types')]
    public function testPhpDocParserReturnsExpectedSyntaxTree(string $source, PhpDocType|null $expected): void
    {
        static::assertEquals($expected, PhpDocTypeParser::parse($source));
    }

    /** @throws JsonException */
    #[DataProvider('objects')]
    public function testDeclaredTypesValidateAndRoundTrip(object $original): void
    {
        static::assertNull(Json::validateType($original::class));
        $json = json_encode($original, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $original::class);

        static::assertEquals($original, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    /** @param class-string $class */
    #[DataProvider('errors')]
    public function testUnsupportedOrMismatchedDeclarationsReturnErrors(
        string $json,
        string $class,
        string $message,
        DecodeErrorKind $kind,
    ): void {
        $decoded = Json::decode($json, $class);
        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame($message, $decoded->getMessage());
        static::assertSame($kind, $decoded->kind);
    }
}
