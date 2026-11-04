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
    /** @var array<string, Node> */
    public array $dynamicAnchors = [];

    /** Whether the root of the resource has `"$recursiveAnchor": true`. */
    public readonly bool $recursiveAnchor;

    public Node|null $rootNode = null;

    /** Whether the compiler has compiled the dynamic anchors and the root of this resource. */
    public bool $prepared = false;

    public function __construct(
        public readonly string $uri,
        public readonly Draft $draft,
        public readonly Vocabulary $vocabulary,
        public readonly stdClass|bool $root,
    ) {
        $this->recursiveAnchor =
            $draft === Draft::Draft201909
            && $root instanceof stdClass
            && (((array) $root)['$recursiveAnchor'] ?? null) === true;
    }
}
