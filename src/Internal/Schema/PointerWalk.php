<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function is_bool;
use function sprintf;

/**
 * Follows the JSON Pointer in the fragment of a reference from the root of a schema resource.
 *
 * @internal
 */
final class PointerWalk
{
    /**
     * @param list<string> $segments
     * @return array{stdClass|bool, Location}
     * @throws SchemaError
     */
    public static function follow(
        stdClass|bool $root,
        Location $location,
        array $segments,
        SchemaIndex $index,
        string $uri,
    ): array {
        $schema = $root;
        foreach ($segments as $segment) {
            /** @var mixed $schema */
            $schema = self::member($schema, $segment, $uri);
            $known = $schema instanceof stdClass ? $index->location($schema) : null;
            $location = $known ?? $location->child($segment);
        }
        if (!$schema instanceof stdClass && !is_bool($schema)) {
            throw new SchemaError(sprintf('The reference %s points to a value that is not a schema.', $uri));
        }
        return [$schema, $location];
    }

    /** @throws SchemaError */
    private static function member(mixed $value, string $segment, string $uri): mixed
    {
        if ($value instanceof stdClass) {
            $members = (array) $value;
            if (array_key_exists($segment, $members)) {
                return $members[$segment];
            }
        }
        $index = (int) $segment;
        if (is_array($value) && array_is_list($value) && (string) $index === $segment) {
            /** @var mixed $item */
            $item = $value[$index] ?? null;
            if ($item !== null) {
                return $item;
            }
        }
        throw new SchemaError(sprintf('The reference %s points to no schema.', $uri));
    }
}
