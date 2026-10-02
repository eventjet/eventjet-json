<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\BackedEnumCaseFinder;
use Eventjet\Json\Internal\BackedEnumValueConverter;
use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassUnionValidator;
use Eventjet\Json\Internal\ConcreteClassUnionValueConverter;
use Eventjet\Json\Internal\ConcreteClassValueConverter;
use Eventjet\Json\Internal\EnumUnionValidator;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\ListItemTypeResolver;
use Eventjet\Json\Internal\ListItemTypeValidator;
use Eventjet\Json\Internal\ListValueConverter;
use Eventjet\Json\Internal\NamedFieldValueConverter;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\ObjectValueConverter;
use Eventjet\Json\Internal\PublicPropertyHydrator;
use Eventjet\Json\Internal\PublicPropertyTypeValidator;
use Eventjet\Json\Internal\PublicPropertyUnionValueConverter;
use Eventjet\Json\Internal\PublicPropertyValueConverter;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\NorthStar\CanonicalJson;
use Eventjet\Json\Test\Acceptance\NorthStar\GitHub\Hydration as GitHubHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\GitHub\PullRequestEvent;
use Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes\Deployment;
use Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes\Hydration as KubernetesHydration;
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
#[CoversClass(ClassFieldTypeValidator::class)]
#[CoversClass(ClassUnionValidator::class)]
#[CoversClass(ConcreteClassUnionValueConverter::class)]
#[CoversClass(ConcreteClassValueConverter::class)]
#[CoversClass(EnumUnionValidator::class)]
#[CoversClass(FieldTypeNameResolver::class)]
#[CoversClass(FieldTypeValidator::class)]
#[CoversClass(ListItemTypeResolver::class)]
#[CoversClass(ListItemTypeValidator::class)]
#[CoversClass(ListValueConverter::class)]
#[CoversClass(NamedFieldValueConverter::class)]
#[CoversClass(ObjectTypeValidator::class)]
#[CoversClass(ObjectValueConverter::class)]
#[CoversClass(ObjectHydrator::class)]
#[CoversClass(PublicPropertyHydrator::class)]
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
        yield 'GitHub pull request webhook' => [
            __DIR__ . '/NorthStar/GitHub/pull-request-opened.json',
            PullRequestEvent::class,
            GitHubHydration::isComplete(...),
        ];

        yield 'Kubernetes deployment' => [
            __DIR__ . '/NorthStar/Kubernetes/deployment.json',
            Deployment::class,
            KubernetesHydration::isComplete(...),
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
