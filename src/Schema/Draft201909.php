<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\Internal\Wither;
use Eventjet\Json\JsonValue;
use Eventjet\Json\OtherMembers;
use JsonSerializable;
use Override;
use ReflectionException;
use UnexpectedValueException;

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
     * Returns a copy of the schema with new values for the given keywords. The other keywords keep their values, and
     * `null` removes a keyword.
     *
     * @param ArrayObject<array-key, bool>|Keep|null $vocabulary
     * @param ArrayObject<array-key, self|bool>|Keep|null $defs
     * @param ArrayObject<array-key, self|bool>|Keep|null $definitions
     * @param list<JsonValue>|Keep|null $examples
     * @param Type|list<Type>|Keep|null $type
     * @param list<JsonValue>|Keep|null $enum
     * @param list<string>|Keep|null $required
     * @param ArrayObject<array-key, list<string>>|Keep|null $dependentRequired
     * @param list<self|bool>|Keep|null $allOf
     * @param list<self|bool>|Keep|null $anyOf
     * @param list<self|bool>|Keep|null $oneOf
     * @param self|bool|list<self|bool>|Keep|null $items
     * @param ArrayObject<array-key, self|bool>|Keep|null $properties
     * @param ArrayObject<array-key, self|bool>|Keep|null $patternProperties
     * @param ArrayObject<array-key, self|bool>|Keep|null $dependentSchemas
     * @param array<array-key, JsonValue>|Keep $unknownKeywords
     * @mago-expect lint:excessive-parameter-list Each keyword of the draft is one parameter.
     * @mago-expect lint:halstead Each keyword of the draft is one argument, so the size grows with the draft.
     */
    public function with(
        string|Keep|null $schema = Keep::Current,
        string|Keep|null $id = Keep::Current,
        string|Keep|null $ref = Keep::Current,
        string|Keep|null $recursiveRef = Keep::Current,
        string|Keep|null $anchor = Keep::Current,
        bool|Keep|null $recursiveAnchor = Keep::Current,
        string|Keep|null $comment = Keep::Current,
        ArrayObject|Keep|null $vocabulary = Keep::Current,
        ArrayObject|Keep|null $defs = Keep::Current,
        ArrayObject|Keep|null $definitions = Keep::Current,
        string|Keep|null $title = Keep::Current,
        string|Keep|null $description = Keep::Current,
        JsonValue|Keep|null $default = Keep::Current,
        bool|Keep|null $deprecated = Keep::Current,
        bool|Keep|null $readOnly = Keep::Current,
        bool|Keep|null $writeOnly = Keep::Current,
        array|Keep|null $examples = Keep::Current,
        Type|array|Keep|null $type = Keep::Current,
        array|Keep|null $enum = Keep::Current,
        JsonValue|Keep|null $const = Keep::Current,
        int|float|Keep|null $multipleOf = Keep::Current,
        int|float|Keep|null $maximum = Keep::Current,
        int|float|Keep|null $exclusiveMaximum = Keep::Current,
        int|float|Keep|null $minimum = Keep::Current,
        int|float|Keep|null $exclusiveMinimum = Keep::Current,
        int|float|Keep|null $maxLength = Keep::Current,
        int|float|Keep|null $minLength = Keep::Current,
        string|Keep|null $pattern = Keep::Current,
        int|float|Keep|null $maxItems = Keep::Current,
        int|float|Keep|null $minItems = Keep::Current,
        int|float|Keep|null $maxContains = Keep::Current,
        int|float|Keep|null $minContains = Keep::Current,
        bool|Keep|null $uniqueItems = Keep::Current,
        int|float|Keep|null $maxProperties = Keep::Current,
        int|float|Keep|null $minProperties = Keep::Current,
        array|Keep|null $required = Keep::Current,
        ArrayObject|Keep|null $dependentRequired = Keep::Current,
        array|Keep|null $allOf = Keep::Current,
        array|Keep|null $anyOf = Keep::Current,
        array|Keep|null $oneOf = Keep::Current,
        self|bool|Keep|null $not = Keep::Current,
        self|bool|Keep|null $if = Keep::Current,
        self|bool|Keep|null $then = Keep::Current,
        self|bool|Keep|null $else = Keep::Current,
        self|bool|array|Keep|null $items = Keep::Current,
        self|bool|Keep|null $additionalItems = Keep::Current,
        self|bool|Keep|null $contains = Keep::Current,
        self|bool|Keep|null $unevaluatedItems = Keep::Current,
        ArrayObject|Keep|null $properties = Keep::Current,
        ArrayObject|Keep|null $patternProperties = Keep::Current,
        self|bool|Keep|null $additionalProperties = Keep::Current,
        ArrayObject|Keep|null $dependentSchemas = Keep::Current,
        self|bool|Keep|null $propertyNames = Keep::Current,
        self|bool|Keep|null $unevaluatedProperties = Keep::Current,
        string|Keep|null $format = Keep::Current,
        string|Keep|null $contentEncoding = Keep::Current,
        string|Keep|null $contentMediaType = Keep::Current,
        self|bool|Keep|null $contentSchema = Keep::Current,
        array|Keep $unknownKeywords = Keep::Current,
    ): self {
        return new self(
            schema: Wither::value($schema, $this->schema),
            id: Wither::value($id, $this->id),
            ref: Wither::value($ref, $this->ref),
            recursiveRef: Wither::value($recursiveRef, $this->recursiveRef),
            anchor: Wither::value($anchor, $this->anchor),
            recursiveAnchor: Wither::value($recursiveAnchor, $this->recursiveAnchor),
            comment: Wither::value($comment, $this->comment),
            vocabulary: Wither::value($vocabulary, $this->vocabulary),
            defs: Wither::value($defs, $this->defs),
            definitions: Wither::value($definitions, $this->definitions),
            title: Wither::value($title, $this->title),
            description: Wither::value($description, $this->description),
            default: Wither::value($default, $this->default),
            deprecated: Wither::value($deprecated, $this->deprecated),
            readOnly: Wither::value($readOnly, $this->readOnly),
            writeOnly: Wither::value($writeOnly, $this->writeOnly),
            examples: Wither::value($examples, $this->examples),
            type: Wither::value($type, $this->type),
            enum: Wither::value($enum, $this->enum),
            const: Wither::value($const, $this->const),
            multipleOf: Wither::value($multipleOf, $this->multipleOf),
            maximum: Wither::value($maximum, $this->maximum),
            exclusiveMaximum: Wither::value($exclusiveMaximum, $this->exclusiveMaximum),
            minimum: Wither::value($minimum, $this->minimum),
            exclusiveMinimum: Wither::value($exclusiveMinimum, $this->exclusiveMinimum),
            maxLength: Wither::value($maxLength, $this->maxLength),
            minLength: Wither::value($minLength, $this->minLength),
            pattern: Wither::value($pattern, $this->pattern),
            maxItems: Wither::value($maxItems, $this->maxItems),
            minItems: Wither::value($minItems, $this->minItems),
            maxContains: Wither::value($maxContains, $this->maxContains),
            minContains: Wither::value($minContains, $this->minContains),
            uniqueItems: Wither::value($uniqueItems, $this->uniqueItems),
            maxProperties: Wither::value($maxProperties, $this->maxProperties),
            minProperties: Wither::value($minProperties, $this->minProperties),
            required: Wither::value($required, $this->required),
            dependentRequired: Wither::value($dependentRequired, $this->dependentRequired),
            allOf: Wither::value($allOf, $this->allOf),
            anyOf: Wither::value($anyOf, $this->anyOf),
            oneOf: Wither::value($oneOf, $this->oneOf),
            not: Wither::value($not, $this->not),
            if: Wither::value($if, $this->if),
            then: Wither::value($then, $this->then),
            else: Wither::value($else, $this->else),
            items: Wither::value($items, $this->items),
            additionalItems: Wither::value($additionalItems, $this->additionalItems),
            contains: Wither::value($contains, $this->contains),
            unevaluatedItems: Wither::value($unevaluatedItems, $this->unevaluatedItems),
            properties: Wither::value($properties, $this->properties),
            patternProperties: Wither::value($patternProperties, $this->patternProperties),
            additionalProperties: Wither::value($additionalProperties, $this->additionalProperties),
            dependentSchemas: Wither::value($dependentSchemas, $this->dependentSchemas),
            propertyNames: Wither::value($propertyNames, $this->propertyNames),
            unevaluatedProperties: Wither::value($unevaluatedProperties, $this->unevaluatedProperties),
            format: Wither::value($format, $this->format),
            contentEncoding: Wither::value($contentEncoding, $this->contentEncoding),
            contentMediaType: Wither::value($contentMediaType, $this->contentMediaType),
            contentSchema: Wither::value($contentSchema, $this->contentSchema),
            unknownKeywords: Wither::value($unknownKeywords, $this->unknownKeywords),
        );
    }

    /**
     * Returns the keywords that the schema has, under their JSON names, followed by its unknown keywords.
     *
     * @throws DecodeError
     * @throws ReflectionException
     * @throws UnexpectedValueException If an unknown keyword has the name of a keyword of the draft, because decoding
     *     would bind it to that keyword.
     */
    #[Override]
    public function jsonSerialize(): object
    {
        return MappedObjectSerializer::serializeWithoutNulls($this);
    }
}
