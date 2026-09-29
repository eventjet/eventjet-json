<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use Eventjet\Json\Field;

/**
 * @api
 */
final readonly class Draft202012
{
    /**
     * @param non-empty-string|null $schema
     * @param non-empty-string|null $anchor
     * @param non-empty-string|null $dynamicAnchor
     * @param array<non-empty-string, bool>|null $vocabulary
     * @param array<array-key, self|bool>|null $defs
     * @param list<mixed>|null $examples
     * @param Type|non-empty-list<Type>|null $type
     * @param list<mixed>|null $enum
     * @param positive-int|float|null $multipleOf
     * @param non-negative-int|float|null $maxLength
     * @param non-negative-int|float|null $minLength
     * @param non-negative-int|float|null $maxItems
     * @param non-negative-int|float|null $minItems
     * @param non-negative-int|float|null $maxContains
     * @param non-negative-int|float|null $minContains
     * @param non-negative-int|float|null $maxProperties
     * @param non-negative-int|float|null $minProperties
     * @param list<string>|null $required
     * @param array<array-key, list<string>>|null $dependentRequired
     * @param non-empty-list<self|bool>|null $allOf
     * @param non-empty-list<self|bool>|null $anyOf
     * @param non-empty-list<self|bool>|null $oneOf
     * @param non-empty-list<self|bool>|null $prefixItems
     * @param array<array-key, self|bool>|null $properties
     * @param array<array-key, self|bool>|null $patternProperties
     * @param array<array-key, self|bool>|null $dependentSchemas
     */
    public function __construct(
        #[Field('$schema')] public string|null $schema = null,
        #[Field('$id')] public string|null $id = null,
        #[Field('$ref')] public string|null $ref = null,
        #[Field('$anchor')] public string|null $anchor = null,
        #[Field('$dynamicRef')] public string|null $dynamicRef = null,
        #[Field('$dynamicAnchor')] public string|null $dynamicAnchor = null,
        #[Field('$comment')] public string|null $comment = null,
        #[Field('$vocabulary')] public array|null $vocabulary = null,
        #[Field('$defs')] public array|null $defs = null,
        public string|null $title = null,
        public string|null $description = null,
        public mixed $default = null,
        public bool|null $deprecated = null,
        public bool|null $readOnly = null,
        public bool|null $writeOnly = null,
        public array|null $examples = null,
        public Type|array|null $type = null,
        public array|null $enum = null,
        public mixed $const = null,
        public int|float|null $multipleOf = null,
        public int|float|null $maximum = null,
        public int|float|null $exclusiveMaximum = null,
        public int|float|null $minimum = null,
        public int|float|null $exclusiveMinimum = null,
        public int|float|null $maxLength = null,
        public int|float|null $minLength = null,
        public string|null $pattern = null,
        public int|float|null $maxItems = null,
        public int|float|null $minItems = null,
        public int|float|null $maxContains = null,
        public int|float|null $minContains = null,
        public bool|null $uniqueItems = null,
        public int|float|null $maxProperties = null,
        public int|float|null $minProperties = null,
        public array|null $required = null,
        public array|null $dependentRequired = null,
        public array|null $allOf = null,
        public array|null $anyOf = null,
        public array|null $oneOf = null,
        public array|null $prefixItems = null,
        public self|bool|null $not = null,
        public self|bool|null $if = null,
        public self|bool|null $then = null,
        public self|bool|null $else = null,
        public self|bool|null $items = null,
        public self|bool|null $contains = null,
        public self|bool|null $additionalProperties = null,
        public self|bool|null $propertyNames = null,
        public self|bool|null $unevaluatedItems = null,
        public self|bool|null $unevaluatedProperties = null,
        public array|null $properties = null,
        public array|null $patternProperties = null,
        public array|null $dependentSchemas = null,
        public string|null $format = null,
        public string|null $contentEncoding = null,
        public string|null $contentMediaType = null,
        public self|bool|null $contentSchema = null,
    ) {
    }
}
