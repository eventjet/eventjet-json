<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use ArrayObject;
use Eventjet\Json\Field;
use Eventjet\Json\JsonValue;
use Eventjet\Json\OtherMembers;
use JsonSerializable;
use Override;
use UnexpectedValueException;

use function array_filter;
use function array_key_exists;
use function sprintf;

/**
 * A JSON Schema of draft 2019-09. Each property holds one keyword, and `null` means that the schema doesn't have it.
 *
 * @api
 */
final readonly class Draft201909 implements JsonSerializable
{
    /**
     * @param ArrayObject<array-key, bool>|null $vocabulary
     * @param ArrayObject<array-key, self|bool>|null $defs
     * @param ArrayObject<array-key, self|bool>|null $definitions
     * @param list<JsonValue>|null $examples
     * @param Type|list<Type>|null $type
     * @param list<JsonValue>|null $enum
     * @param list<string>|null $required
     * @param ArrayObject<array-key, list<string>>|null $dependentRequired
     * @param list<self|bool>|null $allOf
     * @param list<self|bool>|null $anyOf
     * @param list<self|bool>|null $oneOf
     * @param self|bool|list<self|bool>|null $items
     * @param ArrayObject<array-key, self|bool>|null $properties
     * @param ArrayObject<array-key, self|bool>|null $patternProperties
     * @param ArrayObject<array-key, self|bool>|null $dependentSchemas
     * @param array<array-key, JsonValue> $unknownKeywords
     * @mago-expect lint:excessive-parameter-list Each keyword of the draft is one parameter.
     */
    public function __construct(
        #[Field('$schema')]
        public string|null $schema = null,
        #[Field('$id')]
        public string|null $id = null,
        #[Field('$ref')]
        public string|null $ref = null,
        #[Field('$recursiveRef')]
        public string|null $recursiveRef = null,
        #[Field('$anchor')]
        public string|null $anchor = null,
        #[Field('$recursiveAnchor')]
        public bool|null $recursiveAnchor = null,
        #[Field('$comment')]
        public string|null $comment = null,
        #[Field('$vocabulary')]
        public ArrayObject|null $vocabulary = null,
        #[Field('$defs')]
        public ArrayObject|null $defs = null,
        public ArrayObject|null $definitions = null,
        public string|null $title = null,
        public string|null $description = null,
        public JsonValue|null $default = null,
        public bool|null $deprecated = null,
        public bool|null $readOnly = null,
        public bool|null $writeOnly = null,
        public array|null $examples = null,
        public Type|array|null $type = null,
        public array|null $enum = null,
        public JsonValue|null $const = null,
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
        public ArrayObject|null $dependentRequired = null,
        public array|null $allOf = null,
        public array|null $anyOf = null,
        public array|null $oneOf = null,
        public self|bool|null $not = null,
        public self|bool|null $if = null,
        public self|bool|null $then = null,
        public self|bool|null $else = null,
        public self|bool|array|null $items = null,
        public self|bool|null $additionalItems = null,
        public self|bool|null $contains = null,
        public self|bool|null $unevaluatedItems = null,
        public ArrayObject|null $properties = null,
        public ArrayObject|null $patternProperties = null,
        public self|bool|null $additionalProperties = null,
        public ArrayObject|null $dependentSchemas = null,
        public self|bool|null $propertyNames = null,
        public self|bool|null $unevaluatedProperties = null,
        public string|null $format = null,
        public string|null $contentEncoding = null,
        public string|null $contentMediaType = null,
        public self|bool|null $contentSchema = null,
        #[OtherMembers]
        public array $unknownKeywords = [],
    ) {}

    /**
     * Returns the keywords that the schema has, under their JSON names, followed by its unknown keywords.
     *
     * @throws UnexpectedValueException If an unknown keyword has the name of a keyword of the draft, because decoding
     *     would bind it to that keyword.
     */
    #[Override]
    public function jsonSerialize(): object
    {
        $keywords = $this->keywords();
        foreach ($this->unknownKeywords as $name => $_) {
            if (array_key_exists($name, $keywords)) {
                throw new UnexpectedValueException(sprintf(
                    'Cannot encode %s, because its unknown keywords include "%s", which is a keyword of the draft.',
                    self::class,
                    $name,
                ));
            }
        }
        return (object) (
            array_filter($keywords, static fn(mixed $value): bool => $value !== null) + $this->unknownKeywords
        );
    }

    /**
     * Every keyword of the draft under its JSON name, including those that are `null`.
     *
     * @return array<string, mixed>
     */
    private function keywords(): array
    {
        return [
            '$schema' => $this->schema,
            '$id' => $this->id,
            '$ref' => $this->ref,
            '$recursiveRef' => $this->recursiveRef,
            '$anchor' => $this->anchor,
            '$recursiveAnchor' => $this->recursiveAnchor,
            '$comment' => $this->comment,
            '$vocabulary' => $this->vocabulary,
            '$defs' => $this->defs,
            'definitions' => $this->definitions,
            'title' => $this->title,
            'description' => $this->description,
            'default' => $this->default,
            'deprecated' => $this->deprecated,
            'readOnly' => $this->readOnly,
            'writeOnly' => $this->writeOnly,
            'examples' => $this->examples,
            'type' => $this->type,
            'enum' => $this->enum,
            'const' => $this->const,
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
            'maxContains' => $this->maxContains,
            'minContains' => $this->minContains,
            'uniqueItems' => $this->uniqueItems,
            'maxProperties' => $this->maxProperties,
            'minProperties' => $this->minProperties,
            'required' => $this->required,
            'dependentRequired' => $this->dependentRequired,
            'allOf' => $this->allOf,
            'anyOf' => $this->anyOf,
            'oneOf' => $this->oneOf,
            'not' => $this->not,
            'if' => $this->if,
            'then' => $this->then,
            'else' => $this->else,
            'items' => $this->items,
            'additionalItems' => $this->additionalItems,
            'contains' => $this->contains,
            'unevaluatedItems' => $this->unevaluatedItems,
            'properties' => $this->properties,
            'patternProperties' => $this->patternProperties,
            'additionalProperties' => $this->additionalProperties,
            'dependentSchemas' => $this->dependentSchemas,
            'propertyNames' => $this->propertyNames,
            'unevaluatedProperties' => $this->unevaluatedProperties,
            'format' => $this->format,
            'contentEncoding' => $this->contentEncoding,
            'contentMediaType' => $this->contentMediaType,
            'contentSchema' => $this->contentSchema,
        ];
    }
}
