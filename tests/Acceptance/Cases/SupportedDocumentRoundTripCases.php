<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
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
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, callable(object): bool}>
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        foreach (self::factories() as $name => $factory) {
            yield $name => $factory();
        }
    }

    /** @return array<string, Closure(): array{string, class-string, callable(object): bool}> */
    public static function factories(): array
    {
        return [
            'JSON:API compound document' => self::documentFactory(
                __DIR__ . '/../NorthStar/JsonApi/compound-document.json',
                JsonApiDocument::class,
                JsonApiHydration::isComplete(...),
            ),

            'AWS Lambda Amazon MSK event' => self::documentFactory(
                __DIR__ . '/../NorthStar/AwsMsk/event.json',
                MskEvent::class,
                AwsMskHydration::isComplete(...),
            ),

            'Stripe invoice' => self::documentFactory(
                __DIR__ . '/../NorthStar/Stripe/invoice.json',
                Invoice::class,
                StripeHydration::isComplete(...),
            ),

            'GitHub pull request webhook' => self::documentFactory(
                __DIR__ . '/../NorthStar/GitHub/pull-request-opened.json',
                PullRequestEvent::class,
                GitHubHydration::isComplete(...),
            ),

            'Kubernetes deployment' => self::documentFactory(
                __DIR__ . '/../NorthStar/Kubernetes/deployment.json',
                Deployment::class,
                KubernetesHydration::isComplete(...),
            ),
        ];
    }

    /**
     * @param class-string $class
     * @param callable(object): bool $isFullyHydrated
     * @return Closure(): array{string, class-string, callable(object): bool}
     */
    private static function documentFactory(string $path, string $class, callable $isFullyHydrated): Closure
    {
        return (
            /**
             * @return array{string, class-string, callable(object): bool}
             * @throws RuntimeException
             */
            static function () use ($path, $class, $isFullyHydrated): array {
                $json = file_get_contents($path);
                if ($json === false) {
                    throw new RuntimeException('Could not read supported JSON document ' . $path . '.');
                }
                return [$json, $class, $isFullyHydrated];
            }
        );
    }
}
