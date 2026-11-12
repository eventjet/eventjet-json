<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use stdClass;

use function array_combine;
use function array_keys;
use function array_map;
use function array_reverse;
use function in_array;
use function is_array;
use function strval;

/**
 * Rewrites of schema documents that must not change what they mean.
 *
 * @internal
 */
final class SchemaRewrites
{
    /** Keywords whose value is a schema or, for `items`, a list of them. */
    private const array SCHEMAS = [
        'not',
        'if',
        'then',
        'else',
        'items',
        'additionalItems',
        'additionalProperties',
        'contains',
        'propertyNames',
        'unevaluatedItems',
        'unevaluatedProperties',
        'contentSchema',
    ];

    /** Keywords whose value is a list of schemas. */
    private const array LISTS = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

    /** Keywords whose value is an object of schemas or of lists of names. */
    private const array MAPS = [
        'properties',
        'patternProperties',
        '$defs',
        'definitions',
        'dependentSchemas',
        'dependencies',
        'dependentRequired',
    ];

    /**
     * Reverses the parts of a schema whose order has no meaning: the names of `required` and the dependency lists, and
     * the members of the keywords that map names to schemas. Values such as those of `enum` and `const` stay as they
     * are.
     */
    public static function reverse(mixed $schema): mixed
    {
        if (!$schema instanceof stdClass) {
            return $schema;
        }
        $members = (array) $schema;
        $keywords = array_map(strval(...), array_keys($members));
        return (object) array_combine($keywords, array_map(self::reverseKeyword(...), $keywords, $members));
    }

    private static function reverseKeyword(string $keyword, mixed $value): mixed
    {
        $isSchema = in_array($keyword, self::SCHEMAS, strict: true);
        return match (true) {
            $keyword === 'required' && is_array($value) => array_reverse($value),
            ($isSchema || in_array($keyword, self::LISTS, strict: true)) && is_array($value) => array_map(
                self::reverse(...),
                $value,
            ),
            $isSchema => self::reverse($value),
            in_array($keyword, self::MAPS, strict: true) && $value instanceof stdClass => self::reverseMap($value),
            default => $value,
        };
    }

    private static function reverseMap(stdClass $map): object
    {
        return (object) array_map(
            static fn(mixed $value): mixed => is_array($value) ? array_reverse($value) : self::reverse($value),
            array_reverse((array) $map, preserve_keys: true),
        );
    }
}
