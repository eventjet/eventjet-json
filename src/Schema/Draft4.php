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
     * @param array<array-key, JsonValue> $unknownKeywords
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
        #[OtherMembers]
        public array $unknownKeywords = [],
    ) {}

    /**
     * Returns a copy of the schema with new values for the given keywords. The other keywords keep their values, and
     * `null` removes a keyword.
     *
     * @param Type|list<Type>|Keep|null $type
     * @param list<JsonValue>|Keep|null $enum
     * @param list<string>|Keep|null $required
     * @param list<self>|Keep|null $allOf
     * @param list<self>|Keep|null $anyOf
     * @param list<self>|Keep|null $oneOf
     * @param self|list<self>|Keep|null $items
     * @param ArrayObject<array-key, self>|Keep|null $properties
     * @param ArrayObject<array-key, self>|Keep|null $patternProperties
     * @param ArrayObject<array-key, self|list<string>>|Keep|null $dependencies
     * @param ArrayObject<array-key, self>|Keep|null $definitions
     * @param array<array-key, JsonValue>|Keep $unknownKeywords
     * @mago-expect lint:excessive-parameter-list Each keyword of the draft is one parameter.
     */
    public function with(
        string|Keep|null $schema = Keep::Current,
        string|Keep|null $id = Keep::Current,
        string|Keep|null $ref = Keep::Current,
        string|Keep|null $title = Keep::Current,
        string|Keep|null $description = Keep::Current,
        JsonValue|Keep|null $default = Keep::Current,
        Type|array|Keep|null $type = Keep::Current,
        array|Keep|null $enum = Keep::Current,
        int|float|Keep|null $multipleOf = Keep::Current,
        int|float|Keep|null $maximum = Keep::Current,
        bool|Keep|null $exclusiveMaximum = Keep::Current,
        int|float|Keep|null $minimum = Keep::Current,
        bool|Keep|null $exclusiveMinimum = Keep::Current,
        int|float|Keep|null $maxLength = Keep::Current,
        int|float|Keep|null $minLength = Keep::Current,
        string|Keep|null $pattern = Keep::Current,
        int|float|Keep|null $maxItems = Keep::Current,
        int|float|Keep|null $minItems = Keep::Current,
        bool|Keep|null $uniqueItems = Keep::Current,
        int|float|Keep|null $maxProperties = Keep::Current,
        int|float|Keep|null $minProperties = Keep::Current,
        array|Keep|null $required = Keep::Current,
        array|Keep|null $allOf = Keep::Current,
        array|Keep|null $anyOf = Keep::Current,
        array|Keep|null $oneOf = Keep::Current,
        self|Keep|null $not = Keep::Current,
        self|array|Keep|null $items = Keep::Current,
        self|bool|Keep|null $additionalItems = Keep::Current,
        ArrayObject|Keep|null $properties = Keep::Current,
        ArrayObject|Keep|null $patternProperties = Keep::Current,
        self|bool|Keep|null $additionalProperties = Keep::Current,
        ArrayObject|Keep|null $dependencies = Keep::Current,
        string|Keep|null $format = Keep::Current,
        ArrayObject|Keep|null $definitions = Keep::Current,
        array|Keep $unknownKeywords = Keep::Current,
    ): self {
        return new self(
            schema: Wither::value($schema, $this->schema),
            id: Wither::value($id, $this->id),
            ref: Wither::value($ref, $this->ref),
            title: Wither::value($title, $this->title),
            description: Wither::value($description, $this->description),
            default: Wither::value($default, $this->default),
            type: Wither::value($type, $this->type),
            enum: Wither::value($enum, $this->enum),
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
            uniqueItems: Wither::value($uniqueItems, $this->uniqueItems),
            maxProperties: Wither::value($maxProperties, $this->maxProperties),
            minProperties: Wither::value($minProperties, $this->minProperties),
            required: Wither::value($required, $this->required),
            allOf: Wither::value($allOf, $this->allOf),
            anyOf: Wither::value($anyOf, $this->anyOf),
            oneOf: Wither::value($oneOf, $this->oneOf),
            not: Wither::value($not, $this->not),
            items: Wither::value($items, $this->items),
            additionalItems: Wither::value($additionalItems, $this->additionalItems),
            properties: Wither::value($properties, $this->properties),
            patternProperties: Wither::value($patternProperties, $this->patternProperties),
            additionalProperties: Wither::value($additionalProperties, $this->additionalProperties),
            dependencies: Wither::value($dependencies, $this->dependencies),
            format: Wither::value($format, $this->format),
            definitions: Wither::value($definitions, $this->definitions),
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
