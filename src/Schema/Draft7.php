<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Field;
use Eventjet\Json\Internal\MappedObjectSerializer;
use Eventjet\Json\JsonValue;
use Eventjet\Json\OtherMembers;
use JsonSerializable;
use Override;
use ReflectionException;
use UnexpectedValueException;

/**
 * A JSON Schema of draft 7. Each property holds one keyword, and `null` means that the schema doesn't have it.
 *
 * @api
 */
final readonly class Draft7 implements JsonSerializable
{
    /**
     * @param list<JsonValue>|null $examples
     * @param Type|list<Type>|null $type
     * @param list<JsonValue>|null $enum
     * @param list<string>|null $required
     * @param list<self|bool>|null $allOf
     * @param list<self|bool>|null $anyOf
     * @param list<self|bool>|null $oneOf
     * @param self|bool|list<self|bool>|null $items
     * @param ArrayObject<array-key, self|bool>|null $properties
     * @param ArrayObject<array-key, self|bool>|null $patternProperties
     * @param ArrayObject<array-key, self|bool|list<string>>|null $dependencies
     * @param ArrayObject<array-key, self|bool>|null $definitions
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
        #[Field('$comment')]
        public string|null $comment = null,
        public string|null $title = null,
        public string|null $description = null,
        public JsonValue|null $default = null,
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
        public bool|null $uniqueItems = null,
        public int|float|null $maxProperties = null,
        public int|float|null $minProperties = null,
        public array|null $required = null,
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
        public ArrayObject|null $properties = null,
        public ArrayObject|null $patternProperties = null,
        public self|bool|null $additionalProperties = null,
        public ArrayObject|null $dependencies = null,
        public self|bool|null $propertyNames = null,
        public string|null $format = null,
        public string|null $contentEncoding = null,
        public string|null $contentMediaType = null,
        public ArrayObject|null $definitions = null,
        #[OtherMembers]
        public array $unknownKeywords = [],
    ) {}

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
