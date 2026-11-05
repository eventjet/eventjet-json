<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Compile;

use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Keyword;
use Eventjet\Json\Internal\Schema\Keyword\CountLimitKeyword;
use Eventjet\Json\Internal\Schema\Keyword\DependenciesKeyword;
use Eventjet\Json\Internal\Schema\Keyword\PatternKeyword;
use Eventjet\Json\Internal\Schema\Keyword\RequiredKeyword;
use Eventjet\Json\Internal\Schema\Keyword\UniqueItemsKeyword;
use Eventjet\Json\Schema\SchemaError;

/**
 * Compiles the keywords of the validation vocabulary.
 *
 * @internal
 */
final class ValidationKeywords
{
    private const array COUNTS = [
        'maxLength' => ['string', true],
        'minLength' => ['string', false],
        'maxItems' => ['array', true],
        'minItems' => ['array', false],
        'maxProperties' => ['object', true],
        'minProperties' => ['object', false],
    ];

    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    public static function compile(SchemaObject $schema): array
    {
        $keywords = [...AnyTypeKeywords::compile($schema), ...NumericKeywords::compile($schema)];
        foreach (self::COUNTS as $name => [$type, $isMaximum]) {
            $limit = KeywordValues::count($schema, $name);
            if ($limit !== null) {
                $keywords[] = new CountLimitKeyword($name, $type, $limit, $isMaximum, $schema->at($name));
            }
        }
        $pattern = $schema->string('pattern');
        if ($pattern !== null) {
            $location = $schema->at('pattern');
            $keywords[] = new PatternKeyword(KeywordValues::regex($pattern, $location), $pattern, $location);
        }
        if ($schema->get('uniqueItems') === true) {
            $keywords[] = new UniqueItemsKeyword($schema->at('uniqueItems'));
        }
        return [...$keywords, ...self::objectKeywords($schema)];
    }

    /**
     * @return list<Keyword>
     * @throws SchemaError
     */
    private static function objectKeywords(SchemaObject $schema): array
    {
        $keywords = [];
        $required = $schema->array('required');
        if ($required !== null && $required !== []) {
            $location = $schema->at('required');
            $keywords[] = new RequiredKeyword(KeywordValues::strings($required, $location), $location);
        }
        $dependentRequired = $schema->draft->atLeast(Draft::Draft201909) ? $schema->object('dependentRequired') : null;
        if ($dependentRequired !== null) {
            $location = $schema->at('dependentRequired');
            $dependencies = [];
            /** @var mixed $names */
            foreach ((array) $dependentRequired as $member => $names) {
                $dependencies[$member] = KeywordValues::strings($names, $location);
            }
            $keywords[] = new DependenciesKeyword('dependentRequired', $dependencies, $location);
        }
        return $keywords;
    }
}
