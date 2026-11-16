<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Schema\SchemaError;
use SplObjectStorage;

use function sprintf;

/**
 * Finds subschemas that apply to the same value in a loop, such as a `$ref` to the schema that holds it.
 *
 * @internal
 */
final class ReferenceLoops
{
    private const int VISITING = 1;
    private const int DONE = 2;

    /**
     * @param list<Node> $nodes
     * @throws SchemaError
     */
    public static function check(array $nodes): void
    {
        /** @var SplObjectStorage<Node, int> $state */
        $state = new SplObjectStorage();
        foreach ($nodes as $node) {
            self::visit($node, $state);
        }
    }

    /**
     * @param SplObjectStorage<Node, int> $state
     * @throws SchemaError
     */
    private static function visit(Node $node, SplObjectStorage $state): void
    {
        $current = $state[$node] ?? null;
        if ($current === self::DONE) {
            return;
        }
        if ($current === self::VISITING) {
            throw new SchemaError(sprintf(
                'The schema at %s refers to itself without moving into the value, so it would never finish.',
                $node->absoluteLocation,
            ));
        }
        $state[$node] = self::VISITING;
        foreach ($node->keywords as $keyword) {
            foreach ($keyword->inPlace() as $next) {
                self::visit($next, $state);
            }
        }
        $state[$node] = self::DONE;
    }
}
