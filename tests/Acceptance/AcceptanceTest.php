<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\Cases\DecodeErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\NonBackedEnumErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\RootValueErrorCases;
use Eventjet\Json\Test\Acceptance\Cases\RoundTripCases;
use Eventjet\Json\Test\Acceptance\Cases\ScalarTypeMismatchCases;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(ObjectTypeValidator::class)]
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
    public function testDecodeIsTheExactInverseOfJsonEncode(object $original, string|null $json = null): void
    {
        $json ??= json_encode($original, JSON_THROW_ON_ERROR);

        $decoded = Json::decode($json, $original::class);

        static::assertEquals($original, $decoded);
        static::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
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
