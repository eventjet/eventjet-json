<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use ArrayObject;
use Eventjet\Json\Field;
use Eventjet\Json\JsonValue;
use JsonSerializable;
use Override;

use function array_filter;

/**
 * A JSON Schema of draft 4. Each property holds one keyword, and `null` means that the schema doesn't have it.
 *
 * @api
 */
final readonly class Draft4 implements JsonSerializable
{
    /**
     * @param Type|list<Type>|null $type
     * @param list<JsonValue>|null $enum
     * @param list<string>|null $required
     * @param list<self>|null $allOf
     * @param list<self>|null $anyOf
     * @param list<self>|null $oneOf
     * @param self|list<self>|null $items
     * @param ArrayObject<array-key, self>|null $properties
     * @param ArrayObject<array-key, self>|null $patternProperties
     * @param ArrayObject<array-key, self|list<string>>|null $dependencies
     * @param ArrayObject<array-key, self>|null $definitions
     * @mago-expect lint:excessive-parameter-list Each keyword of the draft is one parameter.
     */
    public function __construct(
        #[Field('$schema')]
        public string|null $schema = null,
        public string|null $id = null,
        #[Field('$ref')]
        public string|null $ref = null,
        public string|null $title = null,
        public string|null $description = null,
        public JsonValue|null $default = null,
        public Type|array|null $type = null,
        public array|null $enum = null,
        public int|float|null $multipleOf = null,
        public int|float|null $maximum = null,
        public bool|null $exclusiveMaximum = null,
        public int|float|null $minimum = null,
        public bool|null $exclusiveMinimum = null,
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
        public self|null $not = null,
        public self|array|null $items = null,
        public self|bool|null $additionalItems = null,
        public ArrayObject|null $properties = null,
        public ArrayObject|null $patternProperties = null,
        public self|bool|null $additionalProperties = null,
        public ArrayObject|null $dependencies = null,
        public string|null $format = null,
        public ArrayObject|null $definitions = null,
    ) {}

    /** Returns the keywords that the schema has, under their JSON names. */
    #[Override]
    public function jsonSerialize(): object
    {
        return (object) array_filter(
            [
                '$schema' => $this->schema,
                'id' => $this->id,
                '$ref' => $this->ref,
                'title' => $this->title,
                'description' => $this->description,
                'default' => $this->default,
                'type' => $this->type,
                'enum' => $this->enum,
                'multipleOf' => $this->multipleOf,
                'maximum' => $this->maximum,
                'exclusiveMaximum' => $this->exclusiveMaximum,
                'minimum' => $this->minimum,
                'exclusiveMinimum' => $this->exclusiveMinimum,
                'maxLength' => $this->maxLength,
                'minLength' => $this->minLength,
                'pattern' => $this->pattern,
                'maxItems' => $this->maxItems,
                'minItems' => $this->minItems,
                'uniqueItems' => $this->uniqueItems,
                'maxProperties' => $this->maxProperties,
                'minProperties' => $this->minProperties,
                'required' => $this->required,
                'allOf' => $this->allOf,
                'anyOf' => $this->anyOf,
                'oneOf' => $this->oneOf,
                'not' => $this->not,
                'items' => $this->items,
                'additionalItems' => $this->additionalItems,
                'properties' => $this->properties,
                'patternProperties' => $this->patternProperties,
                'additionalProperties' => $this->additionalProperties,
                'dependencies' => $this->dependencies,
                'format' => $this->format,
                'definitions' => $this->definitions,
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }
}
