<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use InvalidArgumentException;
use Random\Randomizer;

use function in_array;

/**
 * Generates schema documents that are valid against the official meta-schema of their draft, from a seeded Randomizer.
 * A document combines random groups of valid members with keywords that hold generated subschemas.
 *
 * @internal
 */
final class ValidSchemaInputs
{
    private const int MAX_DEPTH = 3;

    private const array ALL = [Draft4::class, Draft6::class, Draft7::class, Draft201909::class, Draft202012::class];

    private const array FROM_6 = [Draft6::class, Draft7::class, Draft201909::class, Draft202012::class];

    private const array FROM_7 = [Draft7::class, Draft201909::class, Draft202012::class];

    private const array FROM_2019_09 = [Draft201909::class, Draft202012::class];

    private const array UP_TO_2019_09 = [Draft4::class, Draft6::class, Draft7::class, Draft201909::class];

    /**
     * Groups of members that are valid together, and the drafts that have them. Draft 4 needs `maximum` and `minimum`
     * next to its boolean `exclusiveMaximum` and `exclusiveMinimum`.
     */
    private const array MEMBERS = [
        [['title' => 'Name', 'description' => 'ä 😀'], self::ALL],
        [['type' => 'string', 'minLength' => 1, 'maxLength' => 10], self::ALL],
        [['type' => ['object', 'null']], self::ALL],
        [['enum' => [1, null, 'a', ['b' => []]]], self::ALL],
        [['pattern' => '^[a-z]+$'], self::ALL],
        [['multipleOf' => 0.5], self::ALL],
        [['minimum' => -1.5, 'maximum' => 10], self::ALL],
        [['minItems' => 0, 'maxItems' => 3, 'uniqueItems' => true], self::ALL],
        [['minProperties' => 1, 'maxProperties' => 2], self::ALL],
        [['required' => ['name']], self::ALL],
        [['format' => 'email'], self::ALL],
        [['dependencies' => ['a' => ['b']]], self::ALL],
        [['$ref' => '#'], self::ALL],
        [['id' => 'https://example.com/a'], [Draft4::class]],
        [['maximum' => 3, 'exclusiveMaximum' => true], [Draft4::class]],
        [['minimum' => 0, 'exclusiveMinimum' => false], [Draft4::class]],
        [['additionalItems' => true], self::UP_TO_2019_09],
        [['$id' => 'https://example.com/a'], self::FROM_6],
        [['$ref' => '#/definitions/a'], self::FROM_6],
        [['exclusiveMinimum' => 0, 'exclusiveMaximum' => 1000.0], self::FROM_6],
        [['const' => ['a' => [1, 2.5]]], self::FROM_6],
        [['examples' => [1, 'a']], self::FROM_6],
        [['required' => []], self::FROM_6],
        [['$comment' => 'A comment.'], self::FROM_7],
        [['readOnly' => true, 'writeOnly' => false], self::FROM_7],
        [['contentMediaType' => 'application/json', 'contentEncoding' => 'base64'], self::FROM_7],
        [['$anchor' => 'node'], self::FROM_2019_09],
        [['dependentRequired' => ['a' => ['b'], 'c' => []]], self::FROM_2019_09],
        [['minContains' => 1, 'maxContains' => 2], self::FROM_2019_09],
        [['deprecated' => true], self::FROM_2019_09],
        [['$recursiveAnchor' => true, '$recursiveRef' => '#'], [Draft201909::class]],
        [['$dynamicAnchor' => 'node', '$dynamicRef' => '#node'], [Draft202012::class]],
    ];

    /** Keywords that hold subschemas, by the shape of their value, and the drafts that have them. */
    private const array APPLICATORS = [
        'schema' => [
            'not' => self::ALL,
            'additionalProperties' => self::ALL,
            'items' => self::ALL,
            'contains' => self::FROM_6,
            'propertyNames' => self::FROM_6,
            'if' => self::FROM_7,
            'then' => self::FROM_7,
            'else' => self::FROM_7,
            'unevaluatedItems' => self::FROM_2019_09,
            'unevaluatedProperties' => self::FROM_2019_09,
        ],
        'list' => [
            'allOf' => self::ALL,
            'anyOf' => self::ALL,
            'oneOf' => self::ALL,
            'items' => self::UP_TO_2019_09,
            'prefixItems' => [Draft202012::class],
        ],
        'map' => [
            'properties' => self::ALL,
            'patternProperties' => self::ALL,
            'definitions' => self::ALL,
            '$defs' => self::FROM_2019_09,
            'dependentSchemas' => self::FROM_2019_09,
        ],
    ];

    /** Property names that are also valid regexes, so that `patternProperties` can use them. */
    private const array NAMES = ['a', 'name', '^x$', '200'];

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $draft
     * @throws InvalidArgumentException
     */
    public static function document(Randomizer $random, string $draft): object
    {
        return (object) self::schema($random, $draft, 0);
    }

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $draft
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function schema(Randomizer $random, string $draft, int $depth): array
    {
        $schema = ['default' => JsonValueInputs::value($random, 0)];
        foreach (self::MEMBERS as [$members, $drafts]) {
            $added = $random->getInt(0, 5) === 0;
            if ($added && in_array($draft, $drafts, strict: true)) {
                $schema = [...$schema, ...$members];
            }
        }
        foreach (self::APPLICATORS as $shape => $keywords) {
            foreach ($keywords as $keyword => $drafts) {
                $added = $depth < self::MAX_DEPTH && $random->getInt(0, 6) === 0;
                if ($added && in_array($draft, $drafts, strict: true)) {
                    $schema[$keyword] = match ($shape) {
                        'schema' => self::subschema($random, $draft, $depth),
                        'list' => self::subschemas($random, $draft, $depth),
                        default => (object) self::namedSubschemas($random, $draft, $depth),
                    };
                }
            }
        }
        return $schema;
    }

    /**
     * A nested schema, and from draft 6 on, sometimes a boolean schema.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $draft
     * @throws InvalidArgumentException
     */
    private static function subschema(Randomizer $random, string $draft, int $depth): object|bool
    {
        $boolean = $draft !== Draft4::class && $random->getInt(0, 3) === 0;
        return $boolean ? SchemaValues::bool($random) : (object) self::schema($random, $draft, $depth + 1);
    }

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $draft
     * @return non-empty-list<object|bool>
     * @throws InvalidArgumentException
     */
    private static function subschemas(Randomizer $random, string $draft, int $depth): array
    {
        $schemas = [self::subschema($random, $draft, $depth)];
        for ($count = $random->getInt(0, 2); $count > 0; $count--) {
            $schemas[] = self::subschema($random, $draft, $depth);
        }
        return $schemas;
    }

    /**
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $draft
     * @return array<array-key, object|bool>
     * @throws InvalidArgumentException
     */
    private static function namedSubschemas(Randomizer $random, string $draft, int $depth): array
    {
        $schemas = [];
        for ($count = $random->getInt(0, 3); $count > 0; $count--) {
            $schemas[JsonValueInputs::pick($random, self::NAMES)] = self::subschema($random, $draft, $depth);
        }
        return $schemas;
    }
}
