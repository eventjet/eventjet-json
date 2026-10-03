<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ArrayObjectMapValueConverter;
use Eventjet\Json\Internal\BackedEnumCaseFinder;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\CollectionTypeResolver;
use Eventjet\Json\Internal\ConcreteClassMapValueConverter;
use Eventjet\Json\Internal\ConcreteClassUnionValueConverter;
use Eventjet\Json\Internal\ConcreteClassValueConverter;
use Eventjet\Json\Internal\EnumUnionValidator;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\ListInputNormalizer;
use Eventjet\Json\Internal\ListItemTypeValidator;
use Eventjet\Json\Internal\ListValueConverter;
use Eventjet\Json\Internal\MapDecodeError;
use Eventjet\Json\Internal\MapInputNormalizer;
use Eventjet\Json\Internal\MapTypeResolver;
use Eventjet\Json\Internal\MapTypeValidator;
use Eventjet\Json\Internal\MapValueConverter;
use Eventjet\Json\Internal\NamedFieldValueConverter;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\ObjectValueConverter;
use Eventjet\Json\Internal\PublicPropertyHydrator;
use Eventjet\Json\Internal\PublicPropertyNamedValueConverter;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\PublicPropertyUnionValueConverter;
use Eventjet\Json\Internal\PublicPropertyValueConverter;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Cases\ConstructorDefaultCases;
use Eventjet\Json\Test\Acceptance\Cases\DecodeErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\NonBackedEnumErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\PublicPropertyUnionRoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\RootValueErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\RoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\ScalarTypeMismatchCases;
use Eventjet\Json\Test\Acceptance\Cases\UnknownFieldCases;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(BackedEnumCaseFinder::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[CoversClass(ArrayObjectMapValueConverter::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[CoversClass(ClassUnionValidator::class)]
#[CoversClass(CollectionTypeResolver::class)]
#[CoversClass(ConcreteClassMapValueConverter::class)]
#[CoversClass(ConcreteClassUnionValueConverter::class)]
#[CoversClass(ConcreteClassValueConverter::class)]
#[CoversClass(EnumUnionValidator::class)]
#[CoversClass(FieldTypeValidator::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(ListItemTypeValidator::class)]
#[CoversClass(ListInputNormalizer::class)]
#[CoversClass(ListValueConverter::class)]
#[CoversClass(MapDecodeError::class)]
#[CoversClass(MapInputNormalizer::class)]
#[CoversClass(MapTypeResolver::class)]
#[CoversClass(MapTypeValidator::class)]
#[CoversClass(MapValueConverter::class)]
#[CoversClass(NamedFieldValueConverter::class)]
#[CoversClass(ObjectTypeValidator::class)]
#[CoversClass(ObjectValueConverter::class)]
#[CoversClass(ObjectHydrator::class)]
#[CoversClass(PublicPropertyHydrator::class)]
#[CoversClass(PublicPropertyNamedValueConverter::class)]
#[CoversClass(PublicPropertyTypeValidator::class)]
#[CoversClass(PublicPropertyUnionValueConverter::class)]
#[CoversClass(PublicPropertyValueConverter::class)]
#[CoversClass(RootTypeValidator::class)]
#[CoversClass(ValueTypeMatcher::class)]
final class AcceptanceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[DataProviderExternal(RoundTripCases::class, 'objects')]
    #[DataProviderExternal(RoundTripCases::class, 'scalarFields')]
    #[DataProviderExternal(RoundTripCases::class, 'numericBoundaries')]
    #[DataProviderExternal(RoundTripCases::class, 'stringFields')]
    #[DataProviderExternal(RoundTripCases::class, 'memberOrders')]
    #[DataProviderExternal(RoundTripCases::class, 'objectRootWhitespace')]
    #[DataProviderExternal(PublicPropertyUnionRoundTripCases::class, 'objects')]
    public function testDecodeIsTheExactInverseOfJsonEncode(object $original, string|null $json = null): void
    {
        $json ??= json_encode($original, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $original::class);

        static::assertEquals($original, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    #[DataProviderExternal(ConstructorDefaultCases::class, 'objects')]
    #[DataProviderExternal(UnknownFieldCases::class, 'objects')]
    public function testDecodeReturnsExpectedObject(string $json, object $expected): void
    {
        $decoded = Json::decode($json, $expected::class);

        static::assertEquals($expected, $decoded);
    }

    /** @param class-string $class */
    #[DataProviderExternal(RootValueErrorCases::class, 'unexpectedRootValues')]
    #[DataProviderExternal(RootValueErrorCases::class, 'arrayRootValues')]
    #[DataProviderExternal(DecodeErrorCases::class, 'constructionFailures')]
    #[DataProviderExternal(DecodeErrorCases::class, 'malformedDocuments')]
    #[DataProviderExternal(DecodeErrorCases::class, 'documentsWithTrailingContent')]
    #[DataProviderExternal(DecodeErrorCases::class, 'invalidUtf8Documents')]
    #[DataProviderExternal(DecodeErrorCases::class, 'deeplyNestedDocuments')]
    #[DataProviderExternal(NonBackedEnumErrorCases::class, 'errors')]
    #[DataProviderExternal(ScalarTypeMismatchCases::class, 'mismatches')]
    #[DataProviderExternal(ScalarTypeMismatchCases::class, 'outOfRangeIntegers')]
    public function testDecodeReturnsErrorsAsValues(string $json, string $class, string $message, int $code): void
    {
        $decoded = Json::decode($json, $class);

        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame($message, $decoded->getMessage());
        static::assertSame($code, $decoded->getCode());
    }

    /** @param class-string $class */
    #[DataProviderExternal(ScalarTypeMismatchCases::class, 'numericKeyMismatches')]
    public function testNumericObjectKeysCannotBypassScalarValidation(string $json, string $class): void
    {
        $decoded = Json::decode($json, $class);

        static::assertTrue($decoded instanceof DecodeError);
        static::assertSame(3, $decoded->getCode());
    }
}
