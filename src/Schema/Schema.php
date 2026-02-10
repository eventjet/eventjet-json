<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use JsonSerializable;
use Override;

final readonly class Schema implements JsonSerializable
{
    private const string UNSET = "\0__UNSET__\0";

    /**
     * @param list<mixed>|null $enum
     * @param array<string, Schema>|null $properties
     * @param list<string>|null $required
     * @param list<Schema>|null $anyOf
     * @param list<Schema>|null $prefixItems
     * @param array<string, Schema>|null $defs
     */
    private function __construct(
        private string|null $type = null,
        private string|null $format = null,
        private string|null $title = null,
        private string|null $description = null,
        /** @var list<mixed>|null */
        private array|null $examples = null,
        private mixed $const = self::UNSET,
        private array|null $enum = null,
        private Schema|null $items = null,
        private array|null $properties = null,
        private array|null $required = null,
        private bool|self|null $additionalProperties = null,
        private array|null $anyOf = null,
        private string|null $ref = null,
        private int|null $minimum = null,
        private int|null $maximum = null,
        private int|null $exclusiveMinimum = null,
        private int|null $exclusiveMaximum = null,
        private int|null $minLength = null,
        private int|null $maxLength = null,
        private int|null $minItems = null,
        private int|null $maxItems = null,
        private bool|null $uniqueItems = null,
        private int|null $minProperties = null,
        private string|null $pattern = null,
        private array|null $prefixItems = null,
        private array|null $defs = null,
        private bool $isMixed = false,
        private bool $isNever = false,
    ) {
    }

    public static function string(): self
    {
        return new self(type: 'string');
    }

    public static function integer(): self
    {
        return new self(type: 'integer');
    }

    public static function number(): self
    {
        return new self(type: 'number');
    }

    public static function boolean(): self
    {
        return new self(type: 'boolean');
    }

    public static function null(): self
    {
        return new self(type: 'null');
    }

    public static function mixed(): self
    {
        return new self(isMixed: true);
    }

    public static function never(): self
    {
        return new self(isNever: true);
    }

    public static function const(mixed $value): self
    {
        return new self(const: $value);
    }

    /**
     * @param list<mixed> $values
     */
    public static function enum(array $values): self
    {
        return new self(enum: $values);
    }

    public static function array(self $items): self
    {
        return new self(type: 'array', items: $items);
    }

    /**
     * @param list<Schema> $prefixItems
     */
    public static function tuple(array $prefixItems): self
    {
        return new self(type: 'array', items: self::never(), prefixItems: $prefixItems);
    }

    /**
     * @param array<string, Schema> $properties
     * @param list<string> $required
     */
    public static function object(array $properties, array $required, bool|self $additionalProperties = false): self
    {
        return new self(
            type: 'object',
            properties: $properties,
            required: $required !== [] ? $required : null,
            additionalProperties: $additionalProperties,
        );
    }

    public static function map(self $valueSchema): self
    {
        return new self(type: 'object', additionalProperties: $valueSchema);
    }

    /**
     * @param list<Schema> $schemas
     */
    public static function anyOf(array $schemas): self
    {
        return new self(anyOf: $schemas);
    }

    public static function ref(string $refPath): self
    {
        return new self(ref: $refPath);
    }

    public function withItems(self|null $items): self
    {
        return new self(
            type: $this->type,
            format: $this->format,
            title: $this->title,
            description: $this->description,
            examples: $this->examples,
            const: $this->const,
            enum: $this->enum,
            items: $items,
            properties: $this->properties,
            required: $this->required,
            additionalProperties: $this->additionalProperties,
            anyOf: $this->anyOf,
            ref: $this->ref,
            minimum: $this->minimum,
            maximum: $this->maximum,
            exclusiveMinimum: $this->exclusiveMinimum,
            exclusiveMaximum: $this->exclusiveMaximum,
            minLength: $this->minLength,
            maxLength: $this->maxLength,
            minItems: $this->minItems,
            maxItems: $this->maxItems,
            uniqueItems: $this->uniqueItems,
            minProperties: $this->minProperties,
            pattern: $this->pattern,
            prefixItems: $this->prefixItems,
            defs: $this->defs,
            isMixed: $this->isMixed,
            isNever: $this->isNever,
        );
    }

    public function withFormat(string $format): self
    {
        return $this->with(format: $format);
    }

    public function withTitle(string $title): self
    {
        return $this->with(title: $title);
    }

    public function withDescription(string $description): self
    {
        return $this->with(description: $description);
    }

    /**
     * @param list<mixed> $examples
     */
    public function withExamples(array $examples): self
    {
        return $this->with(examples: $examples);
    }

    public function withMinimum(int $minimum): self
    {
        return $this->with(minimum: $minimum);
    }

    public function withMaximum(int $maximum): self
    {
        return $this->with(maximum: $maximum);
    }

    public function withExclusiveMinimum(int $exclusiveMinimum): self
    {
        return $this->with(exclusiveMinimum: $exclusiveMinimum);
    }

    public function withExclusiveMaximum(int $exclusiveMaximum): self
    {
        return $this->with(exclusiveMaximum: $exclusiveMaximum);
    }

    public function withMinLength(int $minLength): self
    {
        return $this->with(minLength: $minLength);
    }

    public function withMaxLength(int $maxLength): self
    {
        return $this->with(maxLength: $maxLength);
    }

    public function withMinItems(int $minItems): self
    {
        return $this->with(minItems: $minItems);
    }

    public function withMaxItems(int $maxItems): self
    {
        return $this->with(maxItems: $maxItems);
    }

    public function withUniqueItems(bool $uniqueItems): self
    {
        return $this->with(uniqueItems: $uniqueItems);
    }

    public function withMinProperties(int $minProperties): self
    {
        return $this->with(minProperties: $minProperties);
    }

    public function withPattern(string $pattern): self
    {
        return $this->with(pattern: $pattern);
    }

    /**
     * @param array<string, Schema> $defs
     */
    public function withDefs(array $defs): self
    {
        return $this->with(defs: $defs);
    }

    /**
     * @return bool|object{
     *     '$ref': string,
     *     '$defs'?: array<string, self>,
     * }|object{
     *     '$defs'?: array<string, self>,
     *     title?: string,
     *     description?: string,
     *     type?: string,
     *     format?: string,
     *     const?: mixed,
     *     enum?: list<mixed>,
     *     prefixItems?: list<self>,
     *     items?: self,
     *     properties?: object,
     *     required?: list<string>,
     *     additionalProperties?: bool|self,
     *     anyOf?: list<self>,
     *     minimum?: int,
     *     maximum?: int,
     *     exclusiveMinimum?: int,
     *     exclusiveMaximum?: int,
     *     minLength?: int,
     *     maxLength?: int,
     *     minItems?: int,
     *     maxItems?: int,
     *     uniqueItems?: bool,
     *     minProperties?: int,
     *     pattern?: string,
     *     examples?: list<mixed>,
     * }
     */
    #[Override]
    public function jsonSerialize(): bool|object
    {
        if ($this->isMixed) {
            return true;
        }
        if ($this->isNever) {
            return false;
        }
        if ($this->ref !== null) {
            $result = ['$ref' => $this->ref];
            if ($this->defs !== null) {
                $result['$defs'] = $this->defs;
            }
            return (object)$result;
        }
        $result = [];
        if ($this->defs !== null) {
            $result['$defs'] = $this->defs;
        }
        if ($this->title !== null) {
            $result['title'] = $this->title;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->type !== null) {
            $result['type'] = $this->type;
        }
        if ($this->format !== null) {
            $result['format'] = $this->format;
        }
        if ($this->const !== self::UNSET) {
            /** @psalm-suppress MixedAssignment - const values are intentionally mixed */
            $result['const'] = $this->const;
        }
        if ($this->enum !== null) {
            $result['enum'] = $this->enum;
        }
        if ($this->prefixItems !== null) {
            $result['prefixItems'] = $this->prefixItems;
        }
        if ($this->items !== null) {
            $result['items'] = $this->items;
        }
        if ($this->properties !== null) {
            $result['properties'] = (object)$this->properties;
        }
        if ($this->required !== null) {
            $result['required'] = $this->required;
        }
        if ($this->additionalProperties !== null) {
            $result['additionalProperties'] = $this->additionalProperties;
        }
        if ($this->anyOf !== null) {
            $result['anyOf'] = $this->anyOf;
        }
        if ($this->minimum !== null) {
            $result['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $result['maximum'] = $this->maximum;
        }
        if ($this->exclusiveMinimum !== null) {
            $result['exclusiveMinimum'] = $this->exclusiveMinimum;
        }
        if ($this->exclusiveMaximum !== null) {
            $result['exclusiveMaximum'] = $this->exclusiveMaximum;
        }
        if ($this->minLength !== null) {
            $result['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }
        if ($this->minItems !== null) {
            $result['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $result['maxItems'] = $this->maxItems;
        }
        if ($this->uniqueItems !== null) {
            $result['uniqueItems'] = $this->uniqueItems;
        }
        if ($this->minProperties !== null) {
            $result['minProperties'] = $this->minProperties;
        }
        if ($this->pattern !== null) {
            $result['pattern'] = $this->pattern;
        }
        if ($this->examples !== null) {
            $result['examples'] = $this->examples;
        }
        return (object)$result;
    }

    /**
     * @param list<mixed>|null $examples
     * @param array<string, Schema>|null $defs
     */
    private function with(
        string|null $format = null,
        string|null $title = null,
        string|null $description = null,
        array|null $examples = null,
        int|null $minimum = null,
        int|null $maximum = null,
        int|null $exclusiveMinimum = null,
        int|null $exclusiveMaximum = null,
        int|null $minLength = null,
        int|null $maxLength = null,
        int|null $minItems = null,
        int|null $maxItems = null,
        bool|null $uniqueItems = null,
        int|null $minProperties = null,
        string|null $pattern = null,
        array|null $defs = null,
    ): self {
        return new self(
            type: $this->type,
            format: $format ?? $this->format,
            title: $title ?? $this->title,
            description: $description ?? $this->description,
            examples: $examples ?? $this->examples,
            const: $this->const,
            enum: $this->enum,
            items: $this->items,
            properties: $this->properties,
            required: $this->required,
            additionalProperties: $this->additionalProperties,
            anyOf: $this->anyOf,
            ref: $this->ref,
            minimum: $minimum ?? $this->minimum,
            maximum: $maximum ?? $this->maximum,
            exclusiveMinimum: $exclusiveMinimum ?? $this->exclusiveMinimum,
            exclusiveMaximum: $exclusiveMaximum ?? $this->exclusiveMaximum,
            minLength: $minLength ?? $this->minLength,
            maxLength: $maxLength ?? $this->maxLength,
            minItems: $minItems ?? $this->minItems,
            maxItems: $maxItems ?? $this->maxItems,
            uniqueItems: $uniqueItems ?? $this->uniqueItems,
            minProperties: $minProperties ?? $this->minProperties,
            pattern: $pattern ?? $this->pattern,
            prefixItems: $this->prefixItems,
            defs: $defs ?? $this->defs,
            isMixed: $this->isMixed,
            isNever: $this->isNever,
        );
    }
}
