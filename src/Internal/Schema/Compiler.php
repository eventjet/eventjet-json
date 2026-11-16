<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Schema\Compile\KeywordCompiler;
use Eventjet\Json\Internal\Schema\Compile\ReferenceLoops;
use Eventjet\Json\Internal\Schema\Compile\SchemaObject;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\SchemaError;
use SplObjectStorage;
use stdClass;

use function array_key_exists;
use function is_bool;
use function sprintf;

/**
 * Compiles schema trees into nodes, resolving every reference on the way.
 *
 * @internal
 */
final class Compiler
{
    /** @var SplObjectStorage<stdClass, Node> */
    private SplObjectStorage $nodes;

    /** @var list<Node> */
    private array $compiled = [];

    /** @param bool $intl Whether the intl extension is loaded, which some formats need. */
    public function __construct(
        private readonly Registry $registry,
        public readonly bool $intl,
    ) {
        /** @var SplObjectStorage<stdClass, Node> $nodes */
        $nodes = new SplObjectStorage();
        $this->nodes = $nodes;
    }

    /**
     * Compiles a schema and every schema that it refers to, and returns the node of its root.
     *
     * @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader
     * @param bool $intl Whether the intl extension is loaded, which some formats need.
     * @throws SchemaError
     */
    public static function root(
        Draft4|Draft6|Draft7|Draft201909|Draft202012|bool $schema,
        Closure|null $loader,
        bool $assertFormats,
        bool $intl,
    ): Node {
        $registry = new Registry($loader, $assertFormats);
        $resource = $registry->addSchema($schema, '');
        $compiler = new self($registry, $intl);
        $root = $compiler->compile($resource->root, $registry->rootLocation($resource));
        $compiler->checkLoops();
        return $root;
    }

    /** @throws SchemaError */
    private function compile(stdClass|bool $schema, Location $location): Node
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
    private function checkLoops(): void
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
     * @return array{Node, string|null} The target and the fragment.
     * @throws SchemaError
     */
    public function resolve(string $reference, Location $location): array
    {
        $uri = Uri::resolve($location->base, $reference);
        [$schema, $targetLocation] = $this->registry->locate($uri);
        return [$this->compile($schema, $targetLocation), Uri::split($uri)[1]];
    }

    /**
     * Whether the resource of a target has a `$dynamicAnchor` with the given name.
     *
     * @mutation-free
     */
    public function namesDynamicAnchor(Node $target, string $name): bool
    {
        return array_key_exists($name, $this->registry->dynamicAnchors($target->resource));
    }

    /** @throws SchemaError */
    private function prepare(Resource $resource): void
    {
        foreach ($this->registry->dynamicAnchors($resource) as $name => $schema) {
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
