<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use SplObjectStorage;
use stdClass;

use function is_string;
use function str_starts_with;

/**
 * The location of every subschema in the documents of a compile, and the resources and anchors that they start.
 *
 * @internal
 */
final class SchemaIndex
{
    /** @var SplObjectStorage<stdClass, Location> */
    private SplObjectStorage $locations;

    private Anchors $anchors;

    public function __construct(
        private readonly Registry $registry,
        private readonly Vocabularies $vocabularies,
    ) {
        /** @var SplObjectStorage<stdClass, Location> $locations */
        $locations = new SplObjectStorage();
        $this->locations = $locations;
        $this->anchors = new Anchors();
    }

    public function location(stdClass $schema): Location|null
    {
        return $this->locations[$schema] ?? null;
    }

    public function rootLocation(Resource $resource): Location
    {
        $location = $resource->root instanceof stdClass ? $this->location($resource->root) : null;
        return $location ?? new Location($resource->uri, $resource, '');
    }

    /** @return array<string, stdClass> The subschemas of the resource with a `$dynamicAnchor`, by anchor name. */
    public function dynamicAnchors(Resource $resource): array
    {
        return $this->anchors->dynamic($resource);
    }

    /**
     * @return array{stdClass, Location, bool}|null
     * @mutation-free
     */
    public function anchor(string $uri): array|null
    {
        return $this->anchors->find($uri);
    }

    /**
     * Indexes the root of a document, whose resource the registry has made from its `$id` already.
     *
     * @throws SchemaError
     */
    public function document(stdClass|bool $root, Resource $resource): void
    {
        if (!$root instanceof stdClass) {
            return;
        }
        $location = new Location($resource->uri, $resource, '');
        $id = self::id($root, $resource->draft);
        if ($id !== null) {
            $fragment = Uri::split($id)[1];
            $this->anchors->addId(
                $fragment === null ? $resource->uri : $resource->uri . '#' . $fragment,
                $root,
                $location,
            );
        }
        $this->record($root, $location);
    }

    /** @throws SchemaError */
    private function index(mixed $schema, Location $parent): void
    {
        if (!$schema instanceof stdClass) {
            return;
        }
        $location = $parent;
        $id = self::id($schema, $parent->resource->draft);
        if ($id !== null) {
            $resolved = Uri::resolve($parent->base, $id);
            if (!str_starts_with($id, '#')) {
                $location = $this->scope($schema, Uri::withoutFragment($resolved), $parent);
            }
            $this->anchors->addId($resolved, $schema, $location);
        }
        $this->record($schema, $location);
    }

    /**
     * Returns the location of a subschema with an `$id` that names a URI, which starts a new resource unless it is the
     * URI of the enclosing resource.
     *
     * @throws SchemaError
     */
    private function scope(stdClass $schema, string $uri, Location $parent): Location
    {
        $enclosing = $parent->resource;
        if ($uri === $enclosing->uri) {
            return new Location($uri, $enclosing, $parent->pointer);
        }
        $draft = $enclosing->draft;
        $resource = new Resource($uri, $draft, $this->vocabularies->of($schema, $draft, $enclosing), $schema);
        $this->registry->addResource($resource);
        return new Location($uri, $resource, '');
    }

    /** @throws SchemaError */
    private function record(stdClass $schema, Location $location): void
    {
        $this->locations[$schema] = $location;
        $this->anchors->add($schema, $location);
        /** @var mixed $subschema */
        foreach (Subschemas::of($schema, $location->resource->draft) as [$subschema, $tokens]) {
            $this->index($subschema, $location->child(...$tokens));
        }
    }

    /** Returns the `$id` of a subschema, which a sibling `$ref` overrides before Draft 2019-09. */
    private static function id(stdClass $schema, Draft $draft): string|null
    {
        $refOverrides = !$draft->atLeast(Draft::Draft201909) && is_string(((array) $schema)['$ref'] ?? null);
        return $refOverrides ? null : $draft->id($schema);
    }
}
