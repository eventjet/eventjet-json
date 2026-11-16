<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Discriminator;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\CanonicalScalar;
use Eventjet\Json\Internal\ClassDecoder;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassField;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\CollectionDeclarationError;
use Eventjet\Json\Internal\DiscriminatorCandidates;
use Eventjet\Json\Internal\DiscriminatorInference;
use Eventjet\Json\Internal\DiscriminatorValues;
use Eventjet\Json\Internal\EnumRoots;
use Eventjet\Json\Internal\Error\AmbiguousUnion;
use Eventjet\Json\Internal\Error\ConstructorFailure;
use Eventjet\Json\Internal\Error\DecodeErrors;
use Eventjet\Json\Internal\Error\InvalidDiscriminator;
use Eventjet\Json\Internal\Error\InvalidFieldMapping;
use Eventjet\Json\Internal\Error\InvalidInput;
use Eventjet\Json\Internal\Error\InvalidNumber;
use Eventjet\Json\Internal\Error\InvalidOtherMembers;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedClass;
use Eventjet\Json\Internal\Error\UnsupportedClassUnion;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Eventjet\Json\Internal\Error\UnsupportedJsonValue;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\Internal\FieldDiscriminator;
use Eventjet\Json\Internal\FieldNameRules;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\NamedTypeCompiler;
use Eventjet\Json\Internal\Node\DiscriminatedNode;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\JsonValueNode;
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
use Eventjet\Json\Internal\NodeCompiler;
use Eventjet\Json\Internal\NullableJsonValues;
use Eventjet\Json\Internal\OtherMemberRules;
use Eventjet\Json\Internal\OtherMembersField;
use Eventjet\Json\Internal\OtherMembersMapping;
use Eventjet\Json\Internal\PhpDocArrayShapeParser;
use Eventjet\Json\Internal\PhpDocAtomicTypeParser;
use Eventjet\Json\Internal\PhpDocCallableParser;
use Eventjet\Json\Internal\PhpDocCallableReturnParser;
use Eventjet\Json\Internal\PhpDocChildKind;
use Eventjet\Json\Internal\PhpDocClassNameResolver;
use Eventjet\Json\Internal\PhpDocConstantImportStatement;
use Eventjet\Json\Internal\PhpDocConstantNameParser;
use Eventjet\Json\Internal\PhpDocConstantParser;
use Eventjet\Json\Internal\PhpDocConstantResolver;
use Eventjet\Json\Internal\PhpDocConstantValues;
use Eventjet\Json\Internal\PhpDocDescriptionParser;
use Eventjet\Json\Internal\PhpDocDoctrineArgumentParser;
use Eventjet\Json\Internal\PhpDocDoctrineArrayParser;
use Eventjet\Json\Internal\PhpDocDoctrineParser;
use Eventjet\Json\Internal\PhpDocFieldTagValue;
use Eventjet\Json\Internal\PhpDocFieldType;
use Eventjet\Json\Internal\PhpDocGenericParser;
use Eventjet\Json\Internal\PhpDocHtmlDetector;
use Eventjet\Json\Internal\PhpDocImports;
use Eventjet\Json\Internal\PhpDocImportScanner;
use Eventjet\Json\Internal\PhpDocImportStatement;
use Eventjet\Json\Internal\PhpDocIntegerRange;
use Eventjet\Json\Internal\PhpDocLexer;
use Eventjet\Json\Internal\PhpDocLiteral;
use Eventjet\Json\Internal\PhpDocLiteralNativeType;
use Eventjet\Json\Internal\PhpDocLiteralString;
use Eventjet\Json\Internal\PhpDocMethodParameterTagValue;
use Eventjet\Json\Internal\PhpDocMethodTagValue;
use Eventjet\Json\Internal\PhpDocNamedTypeParser;
use Eventjet\Json\Internal\PhpDocNamespaceDeclaration;
use Eventjet\Json\Internal\PhpDocNarrowing;
use Eventjet\Json\Internal\PhpDocNestedTypeParser;
use Eventjet\Json\Internal\PhpDocOtherTagValue;
use Eventjet\Json\Internal\PhpDocShapeItemParser;
use Eventjet\Json\Internal\PhpDocSimpleTag;
use Eventjet\Json\Internal\PhpDocSimpleTags;
use Eventjet\Json\Internal\PhpDocSimpleType;
use Eventjet\Json\Internal\PhpDocSyntaxError;
use Eventjet\Json\Internal\PhpDocTag;
use Eventjet\Json\Internal\PhpDocTagParser;
use Eventjet\Json\Internal\PhpDocTagValues;
use Eventjet\Json\Internal\PhpDocTextParser;
use Eventjet\Json\Internal\PhpDocTokens;
use Eventjet\Json\Internal\PhpDocTokenState;
use Eventjet\Json\Internal\PhpDocTokenStream;
use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use Eventjet\Json\Internal\PhpDocTypeParser;
use Eventjet\Json\Internal\PhpDocTypeResolver;
use Eventjet\Json\Internal\PhpDocTypeTree;
use Eventjet\Json\Internal\PropertyAttributes;
use Eventjet\Json\Internal\RootDescriptor;
use Eventjet\Json\Internal\TargetClass;
use Eventjet\Json\Internal\TypeCompiler;
use Eventjet\Json\Internal\UnionBuilder;
use Eventjet\Json\Internal\UnionConflicts;
use Eventjet\Json\Json;
use Eventjet\Json\JsonType;
use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\OtherMembers;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Test\Acceptance\Cases\DecodeFailureCases;
use Eventjet\Json\Test\Acceptance\Cases\DecodeSuccess;
use Eventjet\Json\Test\Acceptance\Cases\DecodeSuccessCases;
use Eventjet\Json\Test\Acceptance\Cases\LoadingCases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function array_slice;
use function count;
use function is_string;
use function spl_autoload_register;
use function spl_autoload_unregister;

#[CoversClass(AmbiguousUnion::class)]
#[CoversClass(CanonicalScalar::class)]
#[CoversClass(ClassDecoder::class)]
#[CoversClass(ClassDecoders::class)]
#[CoversClass(ClassField::class)]
#[CoversClass(ClassGraphValidator::class)]
#[CoversClass(CollectionDeclarationError::class)]
#[CoversClass(ConstructorFailure::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(DecodeErrorKind::class)]
#[CoversClass(DecodeErrors::class)]
#[CoversClass(DiscriminatedNode::class)]
#[CoversClass(Discriminator::class)]
#[CoversClass(DiscriminatorCandidates::class)]
#[CoversClass(DiscriminatorInference::class)]
#[CoversClass(DiscriminatorValues::class)]
#[CoversClass(Draft201909::class)]
#[CoversClass(Draft202012::class)]
#[CoversClass(Draft4::class)]
#[CoversClass(Draft6::class)]
#[CoversClass(Draft7::class)]
#[CoversClass(EnumNode::class)]
#[CoversClass(EnumRoots::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldDiscriminator::class)]
#[CoversClass(FieldNameRules::class)]
#[CoversClass(FieldPath::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(InvalidDiscriminator::class)]
#[CoversClass(InvalidFieldMapping::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(InvalidNumber::class)]
#[CoversClass(InvalidOtherMembers::class)]
#[CoversClass(InvalidValue::class)]
#[CoversClass(Json::class)]
#[CoversClass(JsonType::class)]
#[CoversClass(JsonValue::class)]
#[CoversClass(JsonValueNode::class)]
#[CoversClass(ListNode::class)]
#[CoversClass(LiteralNode::class)]
#[CoversClass(MapNode::class)]
#[CoversClass(MappedObjectSerializer::class)]
#[CoversClass(NamedTypeCompiler::class)]
#[CoversClass(NodeCompiler::class)]
#[CoversClass(NullableJsonValues::class)]
#[CoversClass(NullableNode::class)]
#[CoversClass(NullOnlyNode::class)]
#[CoversClass(ObjectNode::class)]
#[CoversClass(OtherMemberRules::class)]
#[CoversClass(OtherMembers::class)]
#[CoversClass(OtherMembersField::class)]
#[CoversClass(OtherMembersMapping::class)]
#[CoversClass(PhpDocArrayShapeParser::class)]
#[CoversClass(PhpDocAtomicTypeParser::class)]
#[CoversClass(PhpDocCallableParser::class)]
#[CoversClass(PhpDocCallableReturnParser::class)]
#[CoversClass(PhpDocChildKind::class)]
#[CoversClass(PhpDocClassNameResolver::class)]
#[CoversClass(PhpDocConstantImportStatement::class)]
#[CoversClass(PhpDocConstantNameParser::class)]
#[CoversClass(PhpDocConstantParser::class)]
#[CoversClass(PhpDocConstantResolver::class)]
#[CoversClass(PhpDocConstantValues::class)]
#[CoversClass(PhpDocDescriptionParser::class)]
#[CoversClass(PhpDocDoctrineArgumentParser::class)]
#[CoversClass(PhpDocDoctrineArrayParser::class)]
#[CoversClass(PhpDocDoctrineParser::class)]
#[CoversClass(PhpDocFieldTagValue::class)]
#[CoversClass(PhpDocFieldType::class)]
#[CoversClass(PhpDocGenericParser::class)]
#[CoversClass(PhpDocHtmlDetector::class)]
#[CoversClass(PhpDocImports::class)]
#[CoversClass(PhpDocImportScanner::class)]
#[CoversClass(PhpDocImportStatement::class)]
#[CoversClass(PhpDocIntegerRange::class)]
#[CoversClass(PhpDocLexer::class)]
#[CoversClass(PhpDocLiteral::class)]
#[CoversClass(PhpDocLiteralNativeType::class)]
#[CoversClass(PhpDocLiteralString::class)]
#[CoversClass(PhpDocMethodParameterTagValue::class)]
#[CoversClass(PhpDocMethodTagValue::class)]
#[CoversClass(PhpDocNamedTypeParser::class)]
#[CoversClass(PhpDocNamespaceDeclaration::class)]
#[CoversClass(PhpDocNarrowing::class)]
#[CoversClass(PhpDocNestedTypeParser::class)]
#[CoversClass(PhpDocOtherTagValue::class)]
#[CoversClass(PhpDocShapeItemParser::class)]
#[CoversClass(PhpDocSimpleTag::class)]
#[CoversClass(PhpDocSimpleTags::class)]
#[CoversClass(PhpDocSimpleType::class)]
#[CoversClass(PhpDocSyntaxError::class)]
#[CoversClass(PhpDocTag::class)]
#[CoversClass(PhpDocTagParser::class)]
#[CoversClass(PhpDocTagValues::class)]
#[CoversClass(PhpDocTextParser::class)]
#[CoversClass(PhpDocTokens::class)]
#[CoversClass(PhpDocTokenState::class)]
#[CoversClass(PhpDocTokenStream::class)]
#[CoversClass(PhpDocTupleEntry::class)]
#[CoversClass(PhpDocType::class)]
#[CoversClass(PhpDocTypeParser::class)]
#[CoversClass(PhpDocTypeResolver::class)]
#[CoversClass(PhpDocTypeTree::class)]
#[CoversClass(PropertyAttributes::class)]
#[CoversClass(RootDescriptor::class)]
#[CoversClass(ScalarItems::class)]
#[CoversClass(ScalarNode::class)]
#[CoversClass(TargetClass::class)]
#[CoversClass(TupleNode::class)]
#[CoversClass(TypeCompiler::class)]
#[CoversClass(UncheckedNode::class)]
#[CoversClass(UnionBuilder::class)]
#[CoversClass(UnionConflicts::class)]
#[CoversClass(UnionNode::class)]
#[CoversClass(UnsupportedClass::class)]
#[CoversClass(UnsupportedClassUnion::class)]
#[CoversClass(UnsupportedCollection::class)]
#[CoversClass(UnsupportedField::class)]
#[CoversClass(UnsupportedFieldClass::class)]
#[CoversClass(UnsupportedJsonValue::class)]
#[CoversClass(UnsupportedTarget::class)]
#[CoversMethod(MappedJsonFields::class, 'jsonSerialize')]
final class AcceptanceTest extends TestCase
{
    #[DataProviderExternal(DecodeSuccessCases::class, 'cases')]
    public function testDecodeSucceeds(DecodeSuccess $case): void
    {
        $json = ($case->json)();
        if ($case->prepare !== null) {
            static::assertTrue(self::decode($json, ($case->type)()) instanceof DecodeError);
            ($case->prepare)();
        }
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            ($case->verify)(self::decode($json, ($case->type)()), $json);
        }
    }

    /** @param class-string|JsonType<list<mixed>|object> $type */
    #[DataProviderExternal(DecodeFailureCases::class, 'failures')]
    public function testDecodeFails(
        string $json,
        string|JsonType $type,
        string $message,
        DecodeErrorKind $kind,
        Throwable|null $previous = null,
    ): void {
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $decoded = self::decode($json, $type);
            static::assertTrue($decoded instanceof DecodeError);
            static::assertSame($message, $decoded->getMessage());
            static::assertSame($kind, $decoded->kind);
            static::assertSame(0, $decoded->getCode());
            static::assertFalse($decoded->getPrevious() instanceof DecodeError);
            if ($previous !== null) {
                static::assertSame($previous, $decoded->getPrevious());
            }
        }
    }

    /** @param Closure(): (class-string|JsonType<list<mixed>|object>) $type */
    #[DataProviderExternal(DecodeSuccessCases::class, 'types')]
    public function testValidateTypeAcceptsEveryDecodableType(Closure $type): void
    {
        static::assertNull(Json::validateType($type())?->getMessage());
    }

    /** @param class-string|JsonType<list<mixed>|object> $type */
    #[DataProviderExternal(DecodeFailureCases::class, 'validations')]
    public function testValidateTypeAgreesWithDecodeFailures(string|JsonType $type, string|null $expected): void
    {
        static::assertSame($expected, Json::validateType($type)?->getMessage());
    }

    /**
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws \RuntimeException
     * @throws TypeError
     */
    #[DataProviderExternal(LoadingCases::class, 'names')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDecodingLoadsOnlyWhatItNeeds(string $name): void
    {
        $steps = LoadingCases::steps($name);
        static::assertNotSame([], $steps);
        $requests = new class {
            /** @var list<string> */
            public array $names = [];
        };
        $autoload = static function (string $class) use ($requests): void {
            $requests->names[] = $class;
        };
        spl_autoload_register($autoload, prepend: true);
        try {
            foreach ($steps as $step) {
                $first = count($requests->names);
                $results = [];
                foreach ($step->decodes as [$json, $type]) {
                    $results[] = self::decode($json, $type);
                }
                $step->assertLoading(array_slice($requests->names, $first));
                foreach ($step->decodes as $index => [2 => $verify]) {
                    $verify($results[$index] ?? null);
                }
            }
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /**
     * @param class-string|JsonType<list<mixed>|object> $type
     * @return list<mixed>|object
     */
    private static function decode(string $json, string|JsonType $type): array|object
    {
        // Separate calls preserve each target's generic type during static analysis.
        return is_string($type) ? Json::decode($json, $type) : Json::decode($json, $type);
    }
}
