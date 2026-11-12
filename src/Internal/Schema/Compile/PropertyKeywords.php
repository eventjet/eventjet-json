<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\AdditionalPropertiesKeyword;
use Eventjet\Json\Internal\Schema\Keyword\PatternPropertiesKeyword;
use Eventjet\Json\Internal\Schema\Keyword\PropertiesKeyword;
use Eventjet\Json\Internal\Schema\Keyword\PropertyNamesKeyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Internal\Schema\SchemaRegex;
use Eventjet\Json\Schema\SchemaError;

/**
 * Compiles the keywords that apply to the members of objects.
 *
 * @internal
 */
final class PropertyKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = [];
        $properties = self::properties($compiler, $schema);
        if ($properties !== null) {
            $keywords[] = new PropertiesKeyword($properties);
        }
        $patterns = self::patternProperties($compiler, $schema);
        if ($patterns !== null) {
            $keywords[] = new PatternPropertiesKeyword($patterns);
        }
        if ($schema->has('additionalProperties')) {
            $keywords[] = self::additionalProperties($compiler, $schema, $properties ?? [], $patterns ?? []);
        }
        if ($schema->draft->atLeast(Draft::Draft6) && $schema->has('propertyNames')) {
            $keywords[] = new PropertyNamesKeyword($schema->subschema($compiler, 'propertyNames'));
        }
        return $keywords;
    }

    /**
     * @return array<array-key, Node>|null The subschemas by member name, or null without `properties`.
     * @throws SchemaError
     */
    private static function properties(Compiler $compiler, SchemaObject $schema): array|null
    {
        $properties = $schema->object('properties');
        if ($properties === null) {
            return null;
        }
        $nodes = [];
        /** @var mixed $subschema */
        foreach ((array) $properties as $name => $subschema) {
            $name = (string) $name;
            $nodes[$name] = $compiler->child($subschema, $schema->location, 'properties', $name);
        }
        return $nodes;
    }

    /**
     * @return list<array{SchemaRegex, Node}>|null The regex and the subschema of each entry, or null without
     *     `patternProperties`.
     * @throws SchemaError
     */
    private static function patternProperties(Compiler $compiler, SchemaObject $schema): array|null
    {
        $patternProperties = $schema->object('patternProperties');
        if ($patternProperties === null) {
            return null;
        }
        $location = $schema->at('patternProperties');
        $entries = [];
        /** @var mixed $subschema */
        foreach ((array) $patternProperties as $pattern => $subschema) {
            $pattern = (string) $pattern;
            $entries[] = [
                KeywordValues::regex($pattern, $location),
                $compiler->child($subschema, $schema->location, 'patternProperties', $pattern),
            ];
        }
        return $entries;
    }

    /**
     * @param array<array-key, Node> $properties
     * @param list<array{SchemaRegex, Node}> $patterns
     * @throws SchemaError
     */
    private static function additionalProperties(
        Compiler $compiler,
        SchemaObject $schema,
        array $properties,
        array $patterns,
    ): AdditionalPropertiesKeyword {
        $names = [];
        foreach ($properties as $name => $_) {
            $names[(string) $name] = true;
        }
        $regexes = [];
        foreach ($patterns as [$regex]) {
            $regexes[] = $regex;
        }
        return new AdditionalPropertiesKeyword($schema->subschema($compiler, 'additionalProperties'), $names, $regexes);
    }
}
