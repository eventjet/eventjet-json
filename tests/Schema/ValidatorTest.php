<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\JsonSchema;
use Eventjet\Json\Schema\SchemaError;
use Eventjet\Json\Schema\ValidationError;
use Eventjet\Json\Schema\ValidationErrorKind;
use Eventjet\Json\Schema\ValidationFailure;
use Eventjet\Json\Schema\Validator;
use Eventjet\Json\Test\Acceptance\Cases\JsonEquality;
use Eventjet\Json\Test\Acceptance\Cases\JsonSchemaCases;
use Eventjet\Json\Test\Acceptance\Cases\SchemaDocumentCases;
use Eventjet\Json\Test\Acceptance\Cases\ValidatorCases;
use Eventjet\Json\Test\Acceptance\Cases\ValidatorErrorCases;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function implode;
use function is_bool;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

#[CoversClass(\Eventjet\Json\Internal\Schema\Anchors::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Annotations::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\AnyTypeKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\ApplicatorKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\DependencyKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\FormatKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\ItemKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\KeywordCompiler::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\KeywordValues::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\NumericKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\PropertyKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\ReferenceKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\ReferenceLoops::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\SchemaObject::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compile\ValidationKeywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Compiler::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Draft::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\EcmaRegex::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\Dates::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\Emails::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\Hosts::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\IdnHosts::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\IdnaContext::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\IpAddresses::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\UriPattern::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Format\Uris::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Formats::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\JsonKey::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\AdditionalPropertiesKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\AllOfKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\AnyOfKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\ContainsKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\ContentKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\CountLimitKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\DependenciesKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\DynamicRefKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\EnumKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\FormatKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\IfKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\ItemsKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\MultipleOfKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\NotKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\NumberLimitKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\OneOfKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\PatternKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\PatternPropertiesKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\PrefixItemsKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\PropertiesKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\PropertyNamesKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\RecursiveRefKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\RefKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\RequiredKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\TypeKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\UnevaluatedItemsKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\UnevaluatedPropertiesKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword\UniqueItemsKeyword::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Keywords::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Location::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\MetaSchemas::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Node::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\PointerWalk::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\Atom::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\AtomEscape::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\CharacterClass::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\CharacterEscape::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\ClassAtom::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\ClassEscape::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\CodePoints::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\Cursor::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\Group::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\Groups::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\Pattern::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\Quantifier::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\UnicodeEscape::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Regex\UnicodeProperties::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Registry::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Resource::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Run::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\SchemaIndex::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\SchemaTree::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Subschemas::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Uri::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\UriPath::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\UriReference::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Vocabularies::class)]
#[CoversClass(\Eventjet\Json\Internal\Schema\Vocabulary::class)]
#[CoversClass(\Eventjet\Json\Schema\Draft201909::class)]
#[CoversClass(\Eventjet\Json\Schema\Draft202012::class)]
#[CoversClass(\Eventjet\Json\Schema\Draft4::class)]
#[CoversClass(\Eventjet\Json\Schema\Draft6::class)]
#[CoversClass(\Eventjet\Json\Schema\Draft7::class)]
#[CoversClass(\Eventjet\Json\Schema\JsonSchema::class)]
#[CoversClass(\Eventjet\Json\Schema\SchemaError::class)]
#[CoversClass(\Eventjet\Json\Schema\Type::class)]
#[CoversClass(\Eventjet\Json\Schema\ValidationError::class)]
#[CoversClass(\Eventjet\Json\Schema\ValidationErrorKind::class)]
#[CoversClass(\Eventjet\Json\Schema\ValidationFailure::class)]
#[CoversClass(\Eventjet\Json\Schema\Validator::class)]
#[UsesClass(\Eventjet\Json\DecodeError::class)]
#[UsesClass(\Eventjet\Json\Internal\CanonicalScalar::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassDecoder::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassDecoders::class)]
#[UsesClass(\Eventjet\Json\Internal\ClassField::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldPath::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldTypeNameResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\MappedObjectSerializer::class)]
#[UsesClass(\Eventjet\Json\Internal\NamedTypeCompiler::class)]
#[UsesClass(\Eventjet\Json\Internal\NodeCompiler::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\EnumNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\JsonValueNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ListNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\MapNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\NullOnlyNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\NullableNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ObjectNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ScalarItems::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\ScalarNode::class)]
#[UsesClass(\Eventjet\Json\Internal\Node\UnionNode::class)]
#[UsesClass(\Eventjet\Json\Internal\OtherMembersField::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocAtomicTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocClassNameResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDescriptionParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocDoctrineParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldTagValue::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocFieldType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocGenericParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImportScanner::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImportStatement::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocImports::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLexer::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNamedTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNamespaceDeclaration::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocNarrowing::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTag::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleTags::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocSimpleType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTag::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTagValues::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTextParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenState::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokenStream::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTokens::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocType::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeParser::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeResolver::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocTypeTree::class)]
#[UsesClass(\Eventjet\Json\Internal\TargetClass::class)]
#[UsesClass(\Eventjet\Json\Internal\TypeCompiler::class)]
#[UsesClass(\Eventjet\Json\Internal\UnionBuilder::class)]
#[UsesClass(\Eventjet\Json\Internal\UnionConflicts::class)]
#[UsesClass(\Eventjet\Json\Json::class)]
#[UsesClass(\Eventjet\Json\JsonValue::class)]
#[UsesClass(\Eventjet\Json\Field::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldNameRules::class)]
#[UsesClass(\Eventjet\Json\Internal\OtherMemberRules::class)]
#[UsesClass(\Eventjet\Json\Internal\OtherMembersMapping::class)]
#[UsesClass(\Eventjet\Json\Internal\PropertyAttributes::class)]
final class ValidatorTest extends TestCase
{
    #[DataProviderExternal(ValidatorCases::class, 'values')]
    public function testBooleanSchemasAcceptEveryValueOrNone(mixed $value): void
    {
        $true = Validator::compile(true);
        $false = Validator::compile(false);
        static::assertTrue($true instanceof Validator);
        static::assertTrue($false instanceof Validator);

        static::assertNull($true->validateValue($value));
        static::assertTrue($true->isValid($value));
        static::assertTrue($false->validateValue($value) instanceof ValidationError);
        static::assertFalse($false->isValid($value));
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    #[DataProviderExternal(ValidatorCases::class, 'valuePairs')]
    public function testConstAcceptsExactlyTheEqualValues(mixed $constant, mixed $value): void
    {
        $validator = Validator::compile(new Draft202012(const: new JsonValue($constant)));
        static::assertTrue($validator instanceof Validator);

        static::assertTrue($validator->isValid($constant));
        static::assertSame(JsonEquality::equal($constant, $value), $validator->isValid($value));
    }

    /** @throws JsonException */
    #[DataProviderExternal(ValidatorCases::class, 'schemasWithValues')]
    public function testValidationReadsValuesTheWayJsonEncodeWritesThem(
        Draft4|Draft6|Draft7|Draft201909|Draft202012 $schema,
        mixed $value,
    ): void {
        $validator = Validator::compile($schema);
        static::assertTrue($validator instanceof Validator);
        $json = json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        static::assertEquals($validator->validateValue(json_decode($json)), $validator->validateValue($value));
    }

    #[DataProviderExternal(ValidatorCases::class, 'schemasWithValues')]
    public function testIsValidAgreesWithValidateValue(
        Draft4|Draft6|Draft7|Draft201909|Draft202012 $schema,
        mixed $value,
    ): void {
        $validator = Validator::compile($schema);
        static::assertTrue($validator instanceof Validator);

        static::assertSame($validator->validateValue($value) === null, $validator->isValid($value));
    }

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @throws RuntimeException
     */
    #[DataProviderExternal(SchemaDocumentCases::class, 'documents')]
    public function testDecodedSchemasMatchTheMetaSchemaOfTheirDraft(string $json, string $class): void
    {
        $schema = JsonSchema::decode($json, $class);
        $metaSchema = JsonSchema::decode(SchemaDocumentCases::metaSchema($schema), $class);
        $validator = $metaSchema instanceof DecodeError ? $metaSchema : Validator::compile($metaSchema);
        static::assertTrue($validator instanceof Validator);

        static::assertNull($validator->validateValue($schema));
    }

    /** @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader */
    #[DataProviderExternal(ValidatorErrorCases::class, 'schemaErrors')]
    public function testCompileReturnsSchemaErrorsAsValues(
        Draft4|Draft6|Draft7|Draft201909|Draft202012|bool $schema,
        Closure|null $loader,
        string $message,
        DecodeError|null $previous,
    ): void {
        $error = Validator::compile($schema, loader: $loader);

        static::assertTrue($error instanceof SchemaError);
        static::assertSame($message, $error->getMessage());
        static::assertSame($previous, $error->getPrevious());
    }

    #[DataProviderExternal(ValidatorErrorCases::class, 'unsupportedValues')]
    public function testValuesThatAreNotJsonReturnErrors(mixed $value): void
    {
        $validator = Validator::compile(true);
        static::assertTrue($validator instanceof Validator);

        $error = $validator->validateValue($value);

        static::assertTrue($error instanceof ValidationError);
        static::assertSame(ValidationErrorKind::UnsupportedValue, $error->kind);
        static::assertSame([], $error->failures);
        static::assertFalse($validator->isValid($value));
    }

    /** @param list<array{string, string, string, string}> $expected */
    #[DataProviderExternal(ValidatorErrorCases::class, 'failures')]
    public function testValidationErrorsListEveryFailure(
        Draft4|Draft6|Draft7|Draft201909|Draft202012|bool $schema,
        mixed $value,
        array $expected,
    ): void {
        $validator = Validator::compile($schema);
        static::assertTrue($validator instanceof Validator);

        $error = $validator->validateValue($value);

        static::assertTrue($error instanceof ValidationError);
        static::assertSame(ValidationErrorKind::InvalidValue, $error->kind);
        $failures = array_map(
            /** @return array{string, string, string, string} */
            static fn(ValidationFailure $failure): array => [
                $failure->instanceLocation,
                $failure->keywordLocation,
                $failure->absoluteKeywordLocation,
                $failure->message,
            ],
            $error->failures,
        );
        static::assertSame($expected, $failures);
        static::assertSame(implode("\n", array_map(strval(...), $error->failures)), (string) $error);
    }

    /**
     * @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>|bool $expected
     */
    #[DataProviderExternal(JsonSchemaCases::class, 'decodedDrafts')]
    public function testJsonSchemaDecodePicksTheClassOfTheDraft(
        string $json,
        Closure|null $loader,
        string|bool $expected,
    ): void {
        $schema = JsonSchema::decode($json, Draft7::class, loader: $loader);

        static::assertSame($expected, is_bool($schema) ? $schema : $schema::class);
    }

    /** @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader */
    #[DataProviderExternal(JsonSchemaCases::class, 'decodeErrors')]
    public function testJsonSchemaDecodeReturnsErrorsAsValues(
        string $json,
        Closure|null $loader,
        string $message,
        DecodeErrorKind $kind,
    ): void {
        $error = JsonSchema::decode($json, Draft202012::class, loader: $loader);

        static::assertTrue($error instanceof DecodeError);
        static::assertSame($message, $error->getMessage());
        static::assertSame($kind, $error->kind);
    }
}
