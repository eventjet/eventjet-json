<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

use function array_key_exists;
use function is_array;

/**
 * Finds the subschemas of a schema object, for indexing their `$id` and anchors.
 *
 * @internal
 */
final class Subschemas
{
    /**
     * Returns the values that hold the subschemas of a schema object, with the reference tokens of their JSON Pointer
     * relative to the schema. Values that aren't schemas are included, so that the caller decides what to skip.
     *
     * @return list<array{mixed, list<string>}>
     */
    public static function of(stdClass $schema, Draft $draft): array
    {
        $members = (array) $schema;
        $subschemas = [];
        foreach (Keywords::single($draft) as $keyword) {
            if (!array_key_exists($keyword, $members)) {
                continue;
            }
            $subschemas[] = [$members[$keyword], [$keyword]];
        }
        foreach (Keywords::lists($draft) as $keyword) {
            /** @var mixed $value */
            $value = $members[$keyword] ?? null;
            if (!is_array($value)) {
                continue;
            }
            /** @var mixed $item */
            foreach ($value as $index => $item) {
                $subschemas[] = [$item, [$keyword, (string) $index]];
            }
        }
        foreach (Keywords::maps($draft) as $keyword) {
            /** @var mixed $value */
            $value = $members[$keyword] ?? null;
            if (!$value instanceof stdClass) {
                continue;
            }
            /** @var mixed $item */
            foreach ((array) $value as $name => $item) {
                $subschemas[] = [$item, [$keyword, (string) $name]];
            }
        }
        return $subschemas;
    }
}
