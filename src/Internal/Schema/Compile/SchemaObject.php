<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Location;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\Uri;
use Eventjet\Json\Internal\Schema\Vocabulary;
use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * A schema object that is being compiled into a node, and the keywords in it.
 *
 * @internal
 */
final readonly class SchemaObject
{
    public Draft $draft;

    public Vocabulary $vocabulary;

    /** @var array<array-key, mixed> */
    private array $members;

    public function __construct(
        public Node $node,
        stdClass $schema,
        public Location $location,
    ) {
        $this->members = (array) $schema;
        $this->draft = $location->resource->draft;
        $this->vocabulary = $location->resource->vocabulary;
    }

    /** @mutation-free */
    public function has(string $keyword): bool
    {
        return array_key_exists($keyword, $this->members);
    }

    /**
     * Returns the value of a keyword, or null if it is missing.
     *
     * @mutation-free
     */
    public function get(string $keyword): mixed
    {
        return $this->members[$keyword] ?? null;
    }

    /**
     * Returns the value of a keyword if it is a string.
     *
     * @mutation-free
     */
    public function string(string $keyword): string|null
    {
        /** @var mixed $value */
        $value = $this->members[$keyword] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Returns the value of a keyword if it is an array.
     *
     * @return array<array-key, mixed>|null
     * @mutation-free
     */
    public function array(string $keyword): array|null
    {
        /** @var mixed $value */
        $value = $this->members[$keyword] ?? null;
        return is_array($value) ? $value : null;
    }

    /**
     * Returns the value of a keyword if it is an object.
     *
     * @mutation-free
     */
    public function object(string $keyword): stdClass|null
    {
        /** @var mixed $value */
        $value = $this->members[$keyword] ?? null;
        return $value instanceof stdClass ? $value : null;
    }

    /** Returns the absolute location of a keyword of the schema, for messages. */
    public function at(string $keyword): string
    {
        return $this->location->absolute() . '/' . Uri::escapePointerToken($keyword);
    }

    /**
     * Compiles the value of a keyword whose value is one schema.
     *
     * @throws SchemaError
     */
    public function subschema(Compiler $compiler, string $keyword): Node
    {
        return $compiler->child($this->members[$keyword] ?? null, $this->location, $keyword);
    }

    /**
     * Compiles the value of a keyword whose value is a list of schemas.
     *
     * @return list<Node>
     * @throws SchemaError
     */
    public function subschemas(Compiler $compiler, string $keyword): array
    {
        /** @var mixed $value */
        $value = $this->members[$keyword] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new SchemaError(sprintf('The keyword at %s must be an array of schemas.', $this->at($keyword)));
        }
        $nodes = [];
        /** @var mixed $subschema */
        foreach ($value as $index => $subschema) {
            $nodes[] = $compiler->child($subschema, $this->location, $keyword, (string) $index);
        }
        return $nodes;
    }
}
