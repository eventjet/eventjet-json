<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\JsonSchema;
use Eventjet\Json\Schema\ValidationError;
use Eventjet\Json\Schema\ValidationFailure;
use Eventjet\Json\Schema\Validator;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;
use function sort;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

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
final class OfficialTestSuiteTest extends TestCase
{
    /**
     * The compiled rewrites of the schema of the last case, by their document. The cases of a group share the schema,
     * so this saves compiling the same rewrites for each of them.
     *
     * @var array<string, Validator>
     */
    private static array $validators = [];

    /** @var array{string, string, array{assertFormats?: true}}|null The schema, class and options of the cache. */
    private static array|null $validatorsFor = null;

    /**
     * Besides the validity, checks the laws that every failure obeys: see {@see KeywordLocationLaw},
     * {@see ApplicatorFailureLaw}, {@see SchemaRewrites} and {@see ApplicatorWrapping}.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @param array{assertFormats?: true} $options
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    #[DataProviderExternal(OfficialSuiteCases::class, 'cases')]
    #[DataProviderExternal(OfficialSuiteCases::class, 'regressions')]
    public function testValidatorAgreesWithTheOfficialTestSuite(
        string $schemaJson,
        string $class,
        mixed $value,
        bool $valid,
        array $options,
    ): void {
        $loader = OfficialSuiteRemotes::loader($class);
        $schema = JsonSchema::decode($schemaJson, $class, loader: $loader);
        static::assertNotInstanceOf(DecodeError::class, $schema);

        $validator = Validator::compile($schema, ...['loader' => $loader, ...$options]);

        static::assertInstanceOf(Validator::class, $validator);
        $error = $validator->validateValue($value);
        static::assertSame($valid, $error === null, (string) $error);
        static::assertSame($valid, $validator->isValid($value));

        $failures = $error->failures ?? [];
        /** @var mixed $document */
        $document = json_decode($schemaJson, flags: JSON_THROW_ON_ERROR);
        foreach ($failures as $failure) {
            KeywordLocationLaw::assertHolds($document, $failure);
            ApplicatorFailureLaw::assertHolds($document, $failure, $failures);
        }
        self::assertRewritesKeepTheFailures($schemaJson, $class, $value, $options, $failures);
    }

    /**
     * Reversing the parts of the schema whose order has no meaning, or wrapping the schema in an applicator, changes the
     * failures only as expected.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @param array{assertFormats?: true} $options
     * @param list<ValidationFailure> $failures The failures of the value against the schema.
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     * @throws JsonException
     */
    private static function assertRewritesKeepTheFailures(
        string $schemaJson,
        string $class,
        mixed $value,
        array $options,
        array $failures,
    ): void {
        if (self::$validatorsFor !== [$schemaJson, $class, $options]) {
            self::$validators = [];
            self::$validatorsFor = [$schemaJson, $class, $options];
        }
        $reversed = self::encode(SchemaRewrites::reverse(json_decode($schemaJson, flags: JSON_THROW_ON_ERROR)));
        $error = self::validate($reversed, $class, $value, $options);
        static::assertSame(self::rows($failures), self::rows($error->failures ?? []), 'reversed');

        foreach (ApplicatorWrappings::of($schemaJson, $value, $class) as $name => $wrapping) {
            $error = self::validate(self::encode($wrapping->schema), $class, $wrapping->value, $options);
            static::assertSame(self::rows($wrapping->failures($failures)), self::rows($error->failures ?? []), $name);
        }
    }

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @param array{assertFormats?: true} $options
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    private static function validate(
        string $schemaJson,
        string $class,
        mixed $value,
        array $options,
    ): ValidationError|null {
        $validator = self::$validators[$schemaJson] ?? null;
        if ($validator === null) {
            $loader = OfficialSuiteRemotes::loader($class);
            $schema = JsonSchema::decode($schemaJson, $class, loader: $loader);
            static::assertNotInstanceOf(DecodeError::class, $schema);
            $validator = Validator::compile($schema, ...['loader' => $loader, ...$options]);
            static::assertInstanceOf(Validator::class, $validator);
            self::$validators[$schemaJson] = $validator;
        }
        return $validator->validateValue($value);
    }

    /** @throws JsonException */
    private static function encode(mixed $json): string
    {
        return json_encode(
            $json,
            JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param list<ValidationFailure> $failures
     * @return list<string> The failures in a form that `assertSame` compares regardless of their order.
     * @throws JsonException
     */
    private static function rows(array $failures): array
    {
        $rows = [];
        foreach ($failures as $failure) {
            $rows[] = self::encode([
                $failure->instanceLocation,
                $failure->keywordLocation,
                $failure->absoluteKeywordLocation,
                $failure->message,
            ]);
        }
        sort($rows);
        return $rows;
    }
}
