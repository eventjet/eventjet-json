<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Internal\Schema\Compile\KeywordCompiler;
use Eventjet\Json\Internal\Schema\Compile\ReferenceLoops;
use Eventjet\Json\Internal\Schema\Compile\SchemaObject;
use Eventjet\Json\Schema\SchemaError;
use SplObjectStorage;
use stdClass;

use function is_bool;
use function sprintf;

/**
 * Compiles schema trees into nodes, resolving every reference on the way.
 *
 * @internal
 */
final class Compiler
{
    /** Whether a schema uses `unevaluatedProperties` or `unevaluatedItems`, which need annotations. */
    public bool $annotates = false;

    /** Whether a schema uses `$dynamicRef` or `$recursiveRef` dynamically, which needs the dynamic scope. */
    public bool $dynamic = false;

    /** @var SplObjectStorage<stdClass, Node> */
    private SplObjectStorage $nodes;

    /** @var list<Node> */
    private array $compiled = [];

    public function __construct(
        private readonly Registry $registry,
    ) {
        /** @var SplObjectStorage<stdClass, Node> $nodes */
        $nodes = new SplObjectStorage();
        $this->nodes = $nodes;
    }

    /** @throws SchemaError */
    public function compile(stdClass|bool $schema, Location $location): Node
    {
        if (is_bool($schema)) {
            return new Node($location->resource, $location->absolute(), $schema);
        }
        $existing = $this->nodes[$schema] ?? null;
        if ($existing !== null) {
            return $existing;
        }
        $node = new Node($location->resource, $location->absolute());
        $this->nodes[$schema] = $node;
        $this->compiled[] = $node;
        $this->prepare($location->resource);
        KeywordCompiler::compile($this, new SchemaObject($node, $schema, $location));
        return $node;
    }

    /**
     * Fails if subschemas refer to each other in a loop without moving into the value, because evaluating them would
     * never end.
     *
     * @throws SchemaError
     */
    public function checkLoops(): void
    {
        ReferenceLoops::check($this->compiled);
    }

    /** @throws SchemaError */
    public function child(mixed $schema, Location $parent, string ...$tokens): Node
    {
        if (!$schema instanceof stdClass && !is_bool($schema)) {
            throw new SchemaError(sprintf('The value at %s must be a schema.', $parent->child(...$tokens)->absolute()));
        }
        $location = $schema instanceof stdClass ? $this->registry->location($schema) : null;
        return $this->compile($schema, $location ?? $parent->child(...$tokens));
    }

    /**
     * @return array{Node, bool, string|null} The target, whether the URI names a `$dynamicAnchor`, and the fragment.
     * @throws SchemaError
     */
    public function resolve(string $reference, Location $location): array
    {
        $uri = Uri::resolve($location->base, $reference);
        [$schema, $targetLocation, $dynamic] = $this->registry->locate($uri);
        return [$this->compile($schema, $targetLocation), $dynamic, Uri::split($uri)[1]];
    }

    /** @throws SchemaError */
    private function prepare(Resource $resource): void
    {
        if ($resource->prepared) {
            return;
        }
        $resource->prepared = true;
        foreach ($resource->dynamicAnchorSchemas as $name => $schema) {
            $location = $this->registry->location($schema);
            if ($location !== null) {
                $resource->dynamicAnchors[$name] = $this->compile($schema, $location);
            }
        }
        if ($resource->recursiveAnchor && $resource->root instanceof stdClass) {
            $location = $this->registry->location($resource->root);
            if ($location !== null) {
                $resource->rootNode = $this->compile($resource->root, $location);
            }
        }
    }
}
