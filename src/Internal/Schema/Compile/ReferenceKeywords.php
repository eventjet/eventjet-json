<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\DynamicRefKeyword;
use Eventjet\Json\Internal\Schema\Keyword\RecursiveRefKeyword;
use Eventjet\Json\Internal\Schema\Keyword\RefKeyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Schema\SchemaError;

/**
 * Compiles `$ref`, `$recursiveRef` and `$dynamicRef`.
 *
 * @internal
 */
final class ReferenceKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = [];
        $ref = $schema->string('$ref');
        if ($ref !== null) {
            $keywords[] = self::ref($compiler, $schema, '$ref', $ref);
        }
        $recursiveRef = $schema->draft === Draft::Draft201909 ? $schema->string('$recursiveRef') : null;
        if ($recursiveRef !== null) {
            $keywords[] = self::recursiveRef($compiler, $schema, $recursiveRef);
        }
        $dynamicRef = $schema->draft === Draft::Draft202012 ? $schema->string('$dynamicRef') : null;
        if ($dynamicRef !== null) {
            $keywords[] = self::dynamicRef($compiler, $schema, $dynamicRef);
        }
        return $keywords;
    }

    /** @throws SchemaError */
    public static function ref(Compiler $compiler, SchemaObject $schema, string $name, string $reference): RefKeyword
    {
        $keyword = new RefKeyword($name);
        [$target] = $compiler->resolve($reference, $schema->location);
        $keyword->target = $target;
        $schema->node->inPlace[] = $target;
        return $keyword;
    }

    /** @throws SchemaError */
    private static function recursiveRef(Compiler $compiler, SchemaObject $schema, string $reference): Keyword
    {
        [$target] = $compiler->resolve($reference, $schema->location);
        $schema->node->inPlace[] = $target;
        if ($target->resource->recursiveAnchor && $target->resource->rootNode === $target) {
            $compiler->dynamic = true;
            return new RecursiveRefKeyword($target);
        }
        return self::staticRef('$recursiveRef', $target);
    }

    /** @throws SchemaError */
    private static function dynamicRef(Compiler $compiler, SchemaObject $schema, string $reference): Keyword
    {
        [$target, $isDynamic, $fragment] = $compiler->resolve($reference, $schema->location);
        $schema->node->inPlace[] = $target;
        if ($isDynamic && $fragment !== null) {
            $compiler->dynamic = true;
            return new DynamicRefKeyword($target, $fragment);
        }
        return self::staticRef('$dynamicRef', $target);
    }

    private static function staticRef(string $name, Node $target): RefKeyword
    {
        $keyword = new RefKeyword($name);
        $keyword->target = $target;
        return $keyword;
    }
}
