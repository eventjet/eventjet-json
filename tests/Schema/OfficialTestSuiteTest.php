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
use Eventjet\Json\Schema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

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
#[CoversClass(\Eventjet\Json\Internal\Schema\Keyword::class)]
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
final class OfficialTestSuiteTest extends TestCase
{
    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[DataProviderExternal(OfficialSuiteCases::class, 'cases')]
    public function testValidatorAgreesWithTheOfficialTestSuite(
        string $schemaJson,
        string $class,
        mixed $value,
        bool $valid,
        bool $assertFormats,
    ): void {
        $loader = OfficialSuiteRemotes::loader($class);
        $schema = JsonSchema::decode($schemaJson, $class, loader: $loader);
        static::assertNotInstanceOf(DecodeError::class, $schema);

        $validator = Validator::compile($schema, loader: $loader, assertFormats: $assertFormats);

        static::assertInstanceOf(Validator::class, $validator);
        $error = $validator->validateValue($value);
        static::assertSame($valid, $error === null, (string) $error);
        static::assertSame($valid, $validator->isValid($value));
    }
}
