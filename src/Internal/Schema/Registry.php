<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function is_bool;
use function sprintf;
use function str_starts_with;

/**
 * The schema resources that a compile knows: the root document, the documents that it references, and every `$id`
 * and anchor in them.
 *
 * @internal
 */
final class Registry
{
    /** @var array<string, Resource> */
    private array $resources = [];

    private readonly Vocabularies $vocabularies;

    private readonly SchemaIndex $index;

    /**
     * @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader
     */
    public function __construct(
        private readonly Closure|null $loader,
        bool $assertFormats,
    ) {
        $this->vocabularies = new Vocabularies($this, $assertFormats);
        $this->index = new SchemaIndex($this, $this->vocabularies);
    }

    /** @throws SchemaError */
    public function addSchema(Draft4|Draft6|Draft7|Draft201909|Draft202012|bool $schema, string $uri): Resource
    {
        if (is_bool($schema)) {
            return $this->addDocument($schema, Draft::Draft202012, $uri);
        }
        return $this->addDocument(SchemaTree::of($schema), Draft::of($schema), $uri);
    }

    /** @throws SchemaError */
    private function addDocument(stdClass|bool $root, Draft $draft, string $uri): Resource
    {
        $id = $root instanceof stdClass ? $draft->id($root) : null;
        $canonical = $id !== null && !str_starts_with($id, '#') ? Uri::withoutFragment(Uri::resolve($uri, $id)) : $uri;
        $resource = new Resource($canonical, $draft, $this->vocabularies->ofDocument($root, $draft, $uri), $root);
        $this->resources[$canonical] = $resource;
        $this->resources[$uri] = $resource;
        $this->index->document($root, $resource);
        return $resource;
    }

    /** Adds the resource of a subschema with its own `$id`. */
    public function addResource(Resource $resource): void
    {
        $this->resources[$resource->uri] = $resource;
    }

    public function location(stdClass $schema): Location|null
    {
        return $this->index->location($schema);
    }

    public function rootLocation(Resource $resource): Location
    {
        return $this->index->rootLocation($resource);
    }

    /**
     * @return array<string, stdClass> The subschemas of the resource with a `$dynamicAnchor`, by anchor name.
     * @mutation-free
     */
    public function dynamicAnchors(Resource $resource): array
    {
        return $this->index->dynamicAnchors($resource);
    }

    /**
     * Finds the subschema that an absolute URI points to, loading its document if needed.
     *
     * @return array{stdClass|bool, Location} The subschema and its location.
     * @throws SchemaError
     */
    public function locate(string $uri): array
    {
        [$resourceUri, $fragment] = Uri::split($uri);
        $tokens = Uri::pointerTokens($fragment ?? '');
        if ($tokens === null) {
            return $this->anchor($uri, $resourceUri);
        }
        $resource = $this->resource($resourceUri);
        [$schema, $location] = PointerWalk::follow(
            $resource->root,
            $this->index->rootLocation($resource),
            $tokens,
            $this->index,
            $uri,
        );
        return [$schema, $location];
    }

    /** @throws SchemaError */
    public function resource(string $uri): Resource
    {
        $resource = $this->resources[$uri] ?? null;
        if ($resource !== null) {
            return $resource;
        }
        $meta = MetaSchemas::load($uri);
        if ($meta !== null) {
            return $this->addDocument($meta[0], $meta[1], $uri);
        }
        if ($this->loader === null) {
            throw new SchemaError(sprintf('Could not resolve %s, because there is no loader.', $uri));
        }
        $document = ($this->loader)($uri);
        if ($document instanceof DecodeError) {
            throw new SchemaError(sprintf('Could not load %s: %s', $uri, $document->getMessage()), $document);
        }
        if ($document === null) {
            throw new SchemaError(sprintf('Could not resolve %s: the loader found no document.', $uri));
        }
        return $this->addSchema($document, $uri);
    }

    /**
     * @return array{stdClass, Location}
     * @throws SchemaError
     */
    private function anchor(string $uri, string $resourceUri): array
    {
        if ($this->index->anchor($uri) === null) {
            $this->resource($resourceUri);
        }
        return (
            $this->index->anchor($uri) ?? throw new SchemaError(sprintf('The reference %s points to no schema.', $uri))
        );
    }
}
