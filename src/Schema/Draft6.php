<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use Eventjet\Json\Field;

/**
 * @api
 */
final readonly class Draft6
{
    /**
     * @param non-empty-string|null $schema
     * @param list<mixed>|null $examples
     * @param Type|non-empty-list<Type>|null $type
     * @param non-empty-list<mixed>|null $enum
     * @param positive-int|float|null $multipleOf
     * @param non-negative-int|float|null $maxLength
     * @param non-negative-int|float|null $minLength
     * @param non-negative-int|float|null $maxItems
     * @param non-negative-int|float|null $minItems
     * @param non-negative-int|float|null $maxProperties
     * @param non-negative-int|float|null $minProperties
     * @param list<string>|null $required
     * @param non-empty-list<self|bool>|null $allOf
     * @param non-empty-list<self|bool>|null $anyOf
     * @param non-empty-list<self|bool>|null $oneOf
     * @param self|bool|non-empty-list<self|bool>|null $items
     * @param array<array-key, self|bool>|null $properties
     * @param array<array-key, self|bool>|null $patternProperties
     * @param array<array-key, self|bool>|null $definitions
     * @param array<array-key, self|bool|list<string>>|null $dependencies
     */
    public function __construct(
        #[Field('$schema')] public string|null $schema = null,
        #[Field('$id')] public string|null $id = null,
        #[Field('$ref')] public string|null $ref = null,
        public string|null $title = null,
        public string|null $description = null,
        public mixed $default = null,
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
        public bool|null $uniqueItems = null,
        public int|float|null $maxProperties = null,
        public int|float|null $minProperties = null,
        public array|null $required = null,
        public array|null $allOf = null,
        public array|null $anyOf = null,
        public array|null $oneOf = null,
        public self|bool|null $not = null,
        public self|bool|array|null $items = null,
        public self|bool|null $contains = null,
        public self|bool|null $additionalProperties = null,
        public self|bool|null $propertyNames = null,
        public array|null $properties = null,
        public array|null $patternProperties = null,
        public string|null $format = null,
        public array|null $definitions = null,
        public array|null $dependencies = null,
        public self|bool|null $additionalItems = null,
    ) {
    }
}
