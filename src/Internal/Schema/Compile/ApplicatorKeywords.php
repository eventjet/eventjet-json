<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\AllOfKeyword;
use Eventjet\Json\Internal\Schema\Keyword\AnyOfKeyword;
use Eventjet\Json\Internal\Schema\Keyword\IfKeyword;
use Eventjet\Json\Internal\Schema\Keyword\NotKeyword;
use Eventjet\Json\Internal\Schema\Keyword\OneOfKeyword;
use Eventjet\Json\Schema\SchemaError;

/**
 * Compiles the keywords of the applicator vocabulary.
 *
 * @internal
 */
final class ApplicatorKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = self::combinations($compiler, $schema);
        if ($schema->has('not')) {
            $keywords[] = new NotKeyword($schema->subschema($compiler, 'not'), $schema->at('not'));
        }
        if ($schema->draft->atLeast(Draft::Draft7) && $schema->has('if')) {
            $keywords[] = self::conditional($compiler, $schema);
        }
        return [
            ...$keywords,
            ...ItemKeywords::compile($compiler, $schema),
            ...PropertyKeywords::compile($compiler, $schema),
            ...DependencyKeywords::compile($compiler, $schema),
        ];
    }

    /**
     * Compiles `allOf`, `anyOf` and `oneOf`.
     *
     * @return list<Keyword>
     * @throws SchemaError
     */
    private static function combinations(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = [];
        foreach (['allOf', 'anyOf', 'oneOf'] as $name) {
            if (!$schema->has($name)) {
                continue;
            }
            $schemas = $schema->subschemas($compiler, $name);
            $keywords[] = match ($name) {
                'allOf' => new AllOfKeyword($schemas),
                'anyOf' => new AnyOfKeyword($schemas, $schema->at($name)),
                'oneOf' => new OneOfKeyword($schemas, $schema->at($name)),
            };
        }
        return $keywords;
    }

    /** @throws SchemaError */
    private static function conditional(Compiler $compiler, SchemaObject $schema): IfKeyword
    {
        $if = $schema->subschema($compiler, 'if');
        $then = $schema->has('then') ? $schema->subschema($compiler, 'then') : null;
        $else = $schema->has('else') ? $schema->subschema($compiler, 'else') : null;
        return new IfKeyword($if, $then, $else);
    }
}
