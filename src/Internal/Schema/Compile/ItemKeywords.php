<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\ContainsKeyword;
use Eventjet\Json\Internal\Schema\Keyword\ItemsKeyword;
use Eventjet\Json\Internal\Schema\Keyword\PrefixItemsKeyword;
use Eventjet\Json\Schema\SchemaError;

use function count;
use function is_array;

/**
 * Compiles the keywords that apply to the items of arrays.
 *
 * @internal
 */
final class ItemKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = $schema->draft === Draft::Draft202012
            ? self::prefixItems($compiler, $schema)
            : self::tupleItems($compiler, $schema);
        if ($schema->draft->atLeast(Draft::Draft6) && $schema->has('contains')) {
            $keywords[] = self::contains($compiler, $schema);
        }
        return $keywords;
    }

    /**
     * Compiles `prefixItems` and `items` of Draft 2020-12.
     *
     * @return list<Keyword>
     * @throws SchemaError
     */
    private static function prefixItems(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = [];
        $prefix = 0;
        if ($schema->has('prefixItems')) {
            $schemas = $schema->subschemas($compiler, 'prefixItems');
            $prefix = count($schemas);
            $keywords[] = new PrefixItemsKeyword('prefixItems', $schemas);
        }
        if ($schema->has('items')) {
            $keywords[] = new ItemsKeyword('items', $schema->subschema($compiler, 'items'), $prefix);
        }
        return $keywords;
    }

    /**
     * Compiles `items`, which is one schema or a list of schemas before Draft 2020-12, and `additionalItems`.
     *
     * @return list<Keyword>
     * @throws SchemaError
     */
    private static function tupleItems(Compiler $compiler, SchemaObject $schema): array
    {
        if (!$schema->has('items')) {
            return [];
        }
        if (!is_array($schema->get('items'))) {
            return [new ItemsKeyword('items', $schema->subschema($compiler, 'items'), start: 0)];
        }
        $schemas = $schema->subschemas($compiler, 'items');
        $keywords = [new PrefixItemsKeyword('items', $schemas)];
        if ($schema->has('additionalItems')) {
            $keywords[] = new ItemsKeyword(
                'additionalItems',
                $schema->subschema($compiler, 'additionalItems'),
                count($schemas),
            );
        }
        return $keywords;
    }

    /** @throws SchemaError */
    private static function contains(Compiler $compiler, SchemaObject $schema): ContainsKeyword
    {
        $min = 1;
        $max = null;
        if ($schema->draft->atLeast(Draft::Draft201909) && $schema->vocabulary->validation) {
            $min = KeywordValues::count($schema, 'minContains') ?? 1;
            $max = KeywordValues::count($schema, 'maxContains');
        }
        return new ContainsKeyword(
            $schema->subschema($compiler, 'contains'),
            $min,
            $max,
            $schema->draft === Draft::Draft202012,
            $schema->at('contains'),
        );
    }
}
