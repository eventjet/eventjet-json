<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

/**
 * A schema resource: a document or a subschema with its own `$id`.
 *
 * @internal
 */
final class Resource
{
    /** @var array<string, stdClass> The subschemas with a `$dynamicAnchor`, by anchor name. */
    public array $dynamicAnchorSchemas = [];

    /** @var array<string, Node> */
    public array $dynamicAnchors = [];

    public bool $recursiveAnchor = false;

    public Node|null $rootNode = null;

    /** Whether the compiler has compiled the dynamic anchors and the root of this resource. */
    public bool $prepared = false;

    public function __construct(
        public readonly string $uri,
        public readonly Draft $draft,
        public readonly Vocabulary $vocabulary,
        public readonly stdClass|bool $root,
    ) {}
}
