<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use SplObjectStorage;
use stdClass;

use function is_string;

/**
 * The subschemas that anchors name, by the URI of the anchor.
 *
 * @internal
 */
final class Anchors
{
    /** @var array<string, array{stdClass, Location}> The subschema and its location. */
    private array $anchors = [];

    /** @var SplObjectStorage<Resource, array<string, stdClass>> */
    private SplObjectStorage $dynamic;

    public function __construct()
    {
        /** @var SplObjectStorage<Resource, array<string, stdClass>> $dynamic */
        $dynamic = new SplObjectStorage();
        $this->dynamic = $dynamic;
    }

    /**
     * @return array{stdClass, Location}|null
     * @mutation-free
     */
    public function find(string $uri): array|null
    {
        return $this->anchors[$uri] ?? null;
    }

    /**
     * @return array<string, stdClass> The subschemas of the resource with a `$dynamicAnchor`, by anchor name.
     * @mutation-free
     */
    public function dynamic(Resource $resource): array
    {
        return $this->dynamic[$resource] ?? [];
    }

    /** Adds the anchor that the fragment of an `$id` names, which only drafts before 2019-09 allow. */
    public function addId(string $resolvedId, stdClass $schema, Location $location): void
    {
        if ($location->resource->draft->atLeast(Draft::Draft201909)) {
            return;
        }
        $this->anchors[$resolvedId] = [$schema, $location];
    }

    /** Adds the `$anchor` and `$dynamicAnchor` of a subschema. */
    public function add(stdClass $schema, Location $location): void
    {
        $resource = $location->resource;
        $draft = $resource->draft;
        $members = (array) $schema;
        /** @var mixed $anchor */
        $anchor = $draft->atLeast(Draft::Draft201909) ? $members['$anchor'] ?? null : null;
        if (is_string($anchor)) {
            $this->anchors[$location->base . '#' . $anchor] = [$schema, $location];
        }
        /** @var mixed $dynamicAnchor */
        $dynamicAnchor = $draft === Draft::Draft202012 ? $members['$dynamicAnchor'] ?? null : null;
        if (is_string($dynamicAnchor)) {
            $this->anchors[$location->base . '#' . $dynamicAnchor] = [$schema, $location];
            $dynamic = $this->dynamic[$resource] ?? [];
            $dynamic[$dynamicAnchor] = $schema;
            $this->dynamic[$resource] = $dynamic;
        }
    }
}
