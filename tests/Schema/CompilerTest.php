<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Internal\Schema\Compile\FormatKeywords;
use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Formats;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\SchemaError;
use Eventjet\Json\Test\Acceptance\Cases\ValidatorErrorCases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Compiler::class)]
#[CoversClass(FormatKeywords::class)]
#[CoversClass(Formats::class)]
#[UsesClass(\Eventjet\Json\Field::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldNameRules::class)]
#[UsesClass(\Eventjet\Json\Internal\MappedObjectSerializer::class)]
#[UsesClass(\Eventjet\Json\Internal\OtherMemberRules::class)]
#[UsesClass(\Eventjet\Json\Internal\OtherMembersMapping::class)]
#[UsesClass(\Eventjet\Json\Internal\PropertyAttributes::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Anchors::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\AnyTypeKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\ApplicatorKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\DependencyKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\ItemKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\KeywordCompiler::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\KeywordValues::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\NumericKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\PropertyKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\ReferenceKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\ReferenceLoops::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\SchemaObject::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Compile\ValidationKeywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Draft::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Format\UriSyntax::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Keyword\FormatKeyword::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Keywords::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Location::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Node::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Registry::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Resource::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\SchemaIndex::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\SchemaTree::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Subschemas::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Uri::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Vocabularies::class)]
#[UsesClass(\Eventjet\Json\Internal\Schema\Vocabulary::class)]
#[UsesClass(\Eventjet\Json\Internal\TargetClass::class)]
#[UsesClass(\Eventjet\Json\Schema\Draft201909::class)]
#[UsesClass(\Eventjet\Json\Schema\Draft202012::class)]
#[UsesClass(\Eventjet\Json\Schema\Draft4::class)]
#[UsesClass(\Eventjet\Json\Schema\Draft6::class)]
#[UsesClass(\Eventjet\Json\Schema\Draft7::class)]
#[UsesClass(\Eventjet\Json\Schema\SchemaError::class)]
final class CompilerTest extends TestCase
{
    #[DataProviderExternal(ValidatorErrorCases::class, 'formatsWithoutIntl')]
    public function testFormatsThatNeedIntlDoNotCompileWithoutIt(
        Draft4|Draft6|Draft7|Draft201909|Draft202012 $schema,
        string|null $message,
    ): void {
        try {
            Compiler::root($schema, null, assertFormats: true, intl: false);
            $actual = null;
        } catch (SchemaError $error) {
            $actual = $error->getMessage();
        }

        static::assertSame($message, $actual);
    }
}
