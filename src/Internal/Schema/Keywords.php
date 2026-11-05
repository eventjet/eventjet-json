<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

/**
 * The keywords of each draft whose values hold subschemas.
 *
 * @internal
 */
final class Keywords
{
    /** @return list<string> Keywords whose value is one schema. */
    public static function single(Draft $draft): array
    {
        return match ($draft) {
            Draft::Draft4 => ['additionalItems', 'additionalProperties', 'not', 'items'],
            Draft::Draft6 => ['additionalItems', 'additionalProperties', 'not', 'items', 'contains', 'propertyNames'],
            Draft::Draft7 => [
                'additionalItems',
                'additionalProperties',
                'not',
                'items',
                'contains',
                'propertyNames',
                'if',
                'then',
                'else',
            ],
            Draft::Draft201909 => [
                'additionalItems',
                'additionalProperties',
                'not',
                'items',
                'contains',
                'propertyNames',
                'if',
                'then',
                'else',
                'unevaluatedItems',
                'unevaluatedProperties',
                'contentSchema',
            ],
            Draft::Draft202012 => [
                'additionalProperties',
                'not',
                'items',
                'contains',
                'propertyNames',
                'if',
                'then',
                'else',
                'unevaluatedItems',
                'unevaluatedProperties',
                'contentSchema',
            ],
        };
    }

    /** @return list<string> Keywords whose value is a list of schemas. */
    public static function lists(Draft $draft): array
    {
        return match ($draft) {
            Draft::Draft202012 => ['allOf', 'anyOf', 'oneOf', 'prefixItems'],
            default => ['allOf', 'anyOf', 'oneOf', 'items'],
        };
    }

    /** @return list<string> Keywords whose value is an object of schemas. */
    public static function maps(Draft $draft): array
    {
        return match ($draft) {
            Draft::Draft4, Draft::Draft6, Draft::Draft7 => [
                'properties',
                'patternProperties',
                'definitions',
                'dependencies',
            ],
            Draft::Draft201909, Draft::Draft202012 => [
                'properties',
                'patternProperties',
                'definitions',
                '$defs',
                'dependentSchemas',
                'dependencies',
            ],
        };
    }
}
