<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\UnevaluatedItemsKeyword;
use Eventjet\Json\Internal\Schema\Keyword\UnevaluatedPropertiesKeyword;
use Eventjet\Json\Schema\SchemaError;

/**
 * Compiles the keywords of a schema object, in the order in which the node checks them.
 *
 * @internal
 */
final class KeywordCompiler
{
    /** @throws SchemaError */
    public static function compile(Compiler $compiler, SchemaObject $schema): void
    {
        $ref = $schema->string('$ref');
        $schema->node->keywords = $ref !== null && !$schema->draft->atLeast(Draft::Draft201909)
            ? [ReferenceKeywords::ref($compiler, $schema, '$ref', $ref)]
            : self::keywords($compiler, $schema);
    }

    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    private static function keywords(Compiler $compiler, SchemaObject $schema): array
    {
        return [
            ...ReferenceKeywords::compile($compiler, $schema),
            ...($schema->vocabulary->validation ? ValidationKeywords::compile($schema) : []),
            ...FormatKeywords::compile($schema),
            ...($schema->vocabulary->applicator ? ApplicatorKeywords::compile($compiler, $schema) : []),
            ...self::unevaluated($compiler, $schema),
        ];
    }

    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    private static function unevaluated(Compiler $compiler, SchemaObject $schema): array
    {
        if (!$schema->vocabulary->unevaluated || !$schema->draft->atLeast(Draft::Draft201909)) {
            return [];
        }
        $keywords = [];
        if ($schema->has('unevaluatedItems')) {
            $compiler->annotates = true;
            $keywords[] = new UnevaluatedItemsKeyword($schema->subschema($compiler, 'unevaluatedItems'));
        }
        if ($schema->has('unevaluatedProperties')) {
            $compiler->annotates = true;
            $keywords[] = new UnevaluatedPropertiesKeyword($schema->subschema($compiler, 'unevaluatedProperties'));
        }
        return $keywords;
    }
}
