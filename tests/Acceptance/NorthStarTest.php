<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ArrayObjectMapValueConverter;
use Eventjet\Json\Internal\BackedEnumCaseFinder;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\CollectionTypeResolver;
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
use Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk\Hydration as AwsMskHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk\MskEvent;
use Eventjet\Json\Test\Acceptance\NorthStar\CanonicalJson;
use Eventjet\Json\Test\Acceptance\NorthStar\JsonApi\Document as JsonApiDocument;
use Eventjet\Json\Test\Acceptance\NorthStar\JsonApi\Hydration as JsonApiHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\Stripe\Hydration as StripeHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\Stripe\Invoice;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Exception as PhpUnitException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function file_get_contents;
use function json_encode;
use function sprintf;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

#[CoversClass(Json::class)]
#[CoversClass(DecodeError::class)]
#[CoversClass(BackedEnumCaseFinder::class)]
#[CoversClass(BackedEnumValueConverter::class)]
#[CoversClass(ArrayObjectMapValueConverter::class)]
#[CoversClass(ClassFieldTypeValidator::class)]
#[CoversClass(ClassUnionValidator::class)]
#[CoversClass(CollectionTypeResolver::class)]
#[CoversClass(ConcreteClassUnionValueConverter::class)]
#[CoversClass(ConcreteClassValueConverter::class)]
#[CoversClass(EnumUnionValidator::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(FieldTypeValidator::class)]
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
final class NorthStarTest extends TestCase
{
    /**
     * @api Called by PHPUnit through DataProvider.
     * @return iterable<string, array{string, class-string, Closure(object): bool}>
     */
    public static function documents(): iterable
    {
        yield 'JSON:API compound document' => [
            __DIR__ . '/NorthStar/JsonApi/compound-document.json',
            JsonApiDocument::class,
            JsonApiHydration::isComplete(...),
        ];

        yield 'AWS Lambda Amazon MSK event' => [
            __DIR__ . '/NorthStar/AwsMsk/event.json',
            MskEvent::class,
            AwsMskHydration::isComplete(...),
        ];

        yield 'Stripe invoice' => [
            __DIR__ . '/NorthStar/Stripe/invoice.json',
            Invoice::class,
            StripeHydration::isComplete(...),
        ];
    }

    /**
     * @param class-string $class
     * @param Closure(object): bool $isFullyHydrated
     * @throws JsonException
     * @throws PhpUnitException
     * @throws UnexpectedValueException
     */
    #[DataProvider('documents')]
    public function testNorthStarHasNotBeenReachedYet(string $path, string $class, Closure $isFullyHydrated): void
    {
        $json = file_get_contents($path);
        static::assertIsString($json);

        $source = CanonicalJson::canonicalize($json);
        $decoded = Json::decode($json, $class);
        $northStarReached = false;

        if (!$decoded instanceof DecodeError) {
            $fullyHydrated = $isFullyHydrated($decoded);

            if ($fullyHydrated) {
                $roundTrip = json_encode($decoded, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                $result = CanonicalJson::canonicalize($roundTrip);
                $northStarReached = $source === $result;
            }
        }

        static::assertFalse($northStarReached, sprintf(
            '%s now hydrates and round-trips. Remove it from NorthStarTest and promote it to acceptance coverage.',
            $path,
        ));
    }
}
