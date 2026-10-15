<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\JsonApi;

use Eventjet\Json\Test\Acceptance\NorthStar\HydrationCheck;

use function array_merge;
use function is_array;

/** @internal */
final class Hydration
{
    public static function isComplete(object $value): bool
    {
        if (!$value instanceof Document || !is_array($value->data)) {
            return false;
        }

        $primaryDataIsHydrated = HydrationCheck::allInstancesAre($value->data, Resource::class);
        $includedDataIsHydrated = HydrationCheck::allInstancesAre($value->included, Resource::class);

        if (!$primaryDataIsHydrated || !$includedDataIsHydrated) {
            return false;
        }

        foreach (array_merge($value->data, $value->included) as $resource) {
            $relationshipsAreComplete = self::relationshipsAreComplete($resource);

            if (!$relationshipsAreComplete) {
                return false;
            }
        }

        return true;
    }

    private static function relationshipsAreComplete(Resource $resource): bool
    {
        $relationships = $resource->relationships ?? null;

        if ($relationships === null) {
            return true;
        }

        $relationshipsAreHydrated = HydrationCheck::allInstancesAre($relationships, Relationship::class);

        if (!$relationshipsAreHydrated) {
            return false;
        }

        foreach ($relationships as $relationship) {
            if (!is_array($relationship->data)) {
                continue;
            }

            $identifiersAreHydrated = HydrationCheck::allInstancesAre($relationship->data, ResourceIdentifier::class);

            if (!$identifiersAreHydrated) {
                return false;
            }
        }

        return true;
    }
}
