<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Compiler;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\DependenciesKeyword;
use Eventjet\Json\Internal\Schema\Node;
use Eventjet\Json\Schema\SchemaError;
use stdClass;

use function is_array;

/**
 * Compiles `dependencies` and `dependentSchemas`.
 *
 * @internal
 */
final class DependencyKeywords
{
    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(Compiler $compiler, SchemaObject $schema): array
    {
        $keywords = [];
        $names = $schema->draft->atLeast(Draft::Draft201909) ? ['dependencies', 'dependentSchemas'] : ['dependencies'];
        foreach ($names as $name) {
            $value = $schema->object($name);
            if ($value === null) {
                continue;
            }
            $dependencies = self::dependencies($compiler, $schema, $name, $value);
            $keywords[] = new DependenciesKeyword($name, $dependencies, $schema->at($name));
        }
        return $keywords;
    }

    /**
     * @return array<array-key, Node|list<string>> The subschema or the required members for each member.
     * @throws SchemaError
     */
    private static function dependencies(Compiler $compiler, SchemaObject $schema, string $name, stdClass $value): array
    {
        $dependencies = [];
        /** @var mixed $dependency */
        foreach ((array) $value as $member => $dependency) {
            $member = (string) $member;
            if ($name === 'dependencies' && is_array($dependency)) {
                $dependencies[$member] = KeywordValues::strings($dependency, $schema->at($name));
                continue;
            }
            $dependencies[$member] = $compiler->child($dependency, $schema->location, $name, $member);
        }
        return $dependencies;
    }
}
