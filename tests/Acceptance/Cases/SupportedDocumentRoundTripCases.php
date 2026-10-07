<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk\Hydration as AwsMskHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\AwsMsk\MskEvent;
use Eventjet\Json\Test\Acceptance\NorthStar\GitHub\Hydration as GitHubHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\GitHub\PullRequestEvent;
use Eventjet\Json\Test\Acceptance\NorthStar\JsonApi\Document as JsonApiDocument;
use Eventjet\Json\Test\Acceptance\NorthStar\JsonApi\Hydration as JsonApiHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes\Deployment;
use Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes\Hydration as KubernetesHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\Stripe\Hydration as StripeHydration;
use Eventjet\Json\Test\Acceptance\NorthStar\Stripe\Invoice;
use RuntimeException;

use function file_get_contents;

/** @internal */
final class SupportedDocumentRoundTripCases
{
    /**
     * @return iterable<string, array{object, string}>
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'JSON:API compound document' => self::document(
            __DIR__ . '/../NorthStar/JsonApi/compound-document.json',
            JsonApiDocument::class,
            JsonApiHydration::isComplete(...),
        );

        yield 'AWS Lambda Amazon MSK event' => self::document(
            __DIR__ . '/../NorthStar/AwsMsk/event.json',
            MskEvent::class,
            AwsMskHydration::isComplete(...),
        );

        yield 'Stripe invoice' => self::document(
            __DIR__ . '/../NorthStar/Stripe/invoice.json',
            Invoice::class,
            StripeHydration::isComplete(...),
        );

        yield 'GitHub pull request webhook' => self::document(
            __DIR__ . '/../NorthStar/GitHub/pull-request-opened.json',
            PullRequestEvent::class,
            GitHubHydration::isComplete(...),
        );

        yield 'Kubernetes deployment' => self::document(
            __DIR__ . '/../NorthStar/Kubernetes/deployment.json',
            Deployment::class,
            KubernetesHydration::isComplete(...),
        );
    }

    /**
     * @param class-string $class
     * @param Closure(object): bool $isFullyHydrated
     * @return array{object, string}
     * @throws RuntimeException
     */
    private static function document(string $path, string $class, Closure $isFullyHydrated): array
    {
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException('Could not read supported JSON document ' . $path . '.');
        }

        $decoded = Json::decode($json, $class);

        if ($decoded instanceof DecodeError) {
            throw new RuntimeException('Could not decode supported JSON document ' . $path . '.', previous: $decoded);
        }

        $fullyHydrated = $isFullyHydrated($decoded);

        if (!$fullyHydrated) {
            throw new RuntimeException('Supported JSON document is not fully hydrated: ' . $path . '.');
        }

        return [$decoded, $json];
    }
}
