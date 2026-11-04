<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

use function is_string;

/**
 * The subschemas that anchors name, by the URI of the anchor.
 *
 * @internal
 */
final class Anchors
{
    /** @var array<string, array{stdClass, Location, bool}> The subschema, its location, and whether it is dynamic. */
    private array $anchors = [];

    /**
     * @return array{stdClass, Location, bool}|null
     * @mutation-free
     */
    public function find(string $uri): array|null
    {
        return $this->anchors[$uri] ?? null;
    }

    /** Adds the anchor that the fragment of an `$id` names, which only drafts before 2019-09 allow. */
    public function addId(string $resolvedId, stdClass $schema, Location $location): void
    {
        $fragment = Uri::split($resolvedId)[1];
        if ($fragment === null || $fragment === '' || $location->resource->draft->atLeast(Draft::Draft201909)) {
            return;
        }
        $this->anchors[$resolvedId] = [$schema, $location, false];
    }

    /** Adds the `$anchor`, `$dynamicAnchor` and `$recursiveAnchor` of a subschema. */
    public function add(stdClass $schema, Location $location): void
    {
        $resource = $location->resource;
        $draft = $resource->draft;
        $members = (array) $schema;
        /** @var mixed $anchor */
        $anchor = $draft->atLeast(Draft::Draft201909) ? $members['$anchor'] ?? null : null;
        if (is_string($anchor)) {
            $this->anchors[$location->base . '#' . $anchor] ??= [$schema, $location, false];
        }
        /** @var mixed $dynamicAnchor */
        $dynamicAnchor = $draft === Draft::Draft202012 ? $members['$dynamicAnchor'] ?? null : null;
        if (is_string($dynamicAnchor)) {
            $this->anchors[$location->base . '#' . $dynamicAnchor] = [$schema, $location, true];
            $resource->dynamicAnchorSchemas[$dynamicAnchor] = $schema;
        }
        if (
            $draft === Draft::Draft201909
            && $resource->root === $schema
            && ($members['$recursiveAnchor'] ?? null) === true
        ) {
            $resource->recursiveAnchor = true;
        }
    }
}
