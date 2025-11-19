<?php

declare(strict_types=1);

namespace Eventjet\Json;

use BackedEnum;
use JsonSerializable;
use LogicException;
use Override;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;
use UnitEnum;

use function class_exists;
use function count;
use function is_a;
use function sprintf;

final readonly class Schema implements JsonSerializable
{
    /**
     * @param 'string'|'number'|'object'|'array'|'boolean'|'null'|'integer'|null $type
     * @param array<string, self> $properties
     * @param list<string> $required
     * @param list<string|int> $enum
     */
    private function __construct(
        public string|null $type = null,
        public array $properties = [],
        public array $required = [],
        public bool|null $additionalProperties = null,
        public mixed $const = null,
        public array $enum = [],
        public self|null $items = null,
    ) {

    }

    public static function inferFromType(string|ArrayOf $type): self|Throwable
    {
        if ($type instanceof ArrayOf) {
            $itemSchema = self::inferFromType($type->itemType);
            if ($itemSchema instanceof Throwable) {
                return new LogicException(
                    sprintf(
                        'Failed to infer schema for array items: %s',
                        $itemSchema->getMessage(),
                    ),
                    previous: $itemSchema,
                );
            }
            return self::array($itemSchema);
        }
        return match ($type) {
            'string' => self::string(),
            'int' => self::integer(),
            'float' => self::number(),
            'bool' => self::boolean(),
            'true' => self::const(true),
            'false' => self::const(false),
            'null' => self::null(),
            'mixed' => new self(),
            default => class_exists($type)
                ? self::inferFromClassName($type)
                : new LogicException(sprintf('Cannot infer schema: class %s does not exist.', $type)),
        };
    }

    /**
     * @param class-string $type
     */
    private static function inferFromClassName(string $type): self|Throwable
    {
        if (is_a($type, UnitEnum::class, true)) {
            return self::inferFromEnum($type);
        }
        $constructor = (new ReflectionClass($type))->getConstructor();
        $properties = [];
        $required = [];
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $paramType = $parameter->getType();
                if ($paramType === null) {
                    return new LogicException(sprintf(
                        'Property %s::%s has no type. If you want it to accept anything, use "mixed" as type.',
                        $type,
                        $parameter->getName(),
                    ));
                }
                if (!$paramType instanceof ReflectionNamedType) {
                    return new LogicException(sprintf(
                        'Property %s::%s has an unsupported union or intersection type.',
                        $type,
                        $parameter->getName(),
                    ));
                }
                $paramTypeName = $paramType->getName();
                if ($paramTypeName === 'array') {
                    $itemType = self::inferFromArrayParameter($parameter);
                    if ($itemType instanceof Throwable) {
                        return new LogicException(
                            sprintf(
                                'Failed to infer schema for array parameter %s::%s: %s',
                                $type,
                                $parameter->getName(),
                                $itemType->getMessage(),
                            ),
                            previous: $itemType,
                        );
                    }
                    $properties[$parameter->getName()] = $itemType;
                    if (!$parameter->isOptional()) {
                        $required[] = $parameter->getName();
                    }
                    continue;
                }
                $propertySchema = self::inferFromType($paramTypeName);
                if ($propertySchema instanceof Throwable) {
                    return new LogicException(
                        sprintf(
                            'Failed to infer schema for property %s::%s: %s',
                            $type,
                            $parameter->getName(),
                            $propertySchema->getMessage(),
                        ),
                        previous: $propertySchema,
                    );
                }
                $properties[$parameter->getName()] = $propertySchema;
                if (!$parameter->isOptional()) {
                    $required[] = $parameter->getName();
                }
            }
        }
        return new self('object', $properties, $required, additionalProperties: false);
    }

    private static function string(): self
    {
        return new self('string');
    }

    private static function integer(): self
    {
        return new self('integer');
    }

    private static function number(): self
    {
        return new self('number');
    }

    private static function boolean(): self
    {
        return new self('boolean');
    }

    private static function const(mixed $value): self
    {
        return new self(const: $value);
    }

    private static function null(): self
    {
        return new self('null');
    }

    /**
     * @param class-string<UnitEnum> $type
     */
    private static function inferFromEnum(string $type): self|Throwable
    {
        if (!is_a($type, BackedEnum::class, true)) {
            return new LogicException(sprintf('Only backed enums are supported, %s is not.', $type));
        }
        $values = [];
        foreach ($type::cases() as $case) {
            /** @var string|int $value */
            $value = $case->value;
            $values[] = $value;
        }
        return new self(enum: $values);
    }

    private static function inferFromArrayParameter(ReflectionParameter $parameter): self|Throwable
    {
        $attributes = $parameter->getAttributes(ArrayOf::class);
        $nAttributes = count($attributes);
        if ($nAttributes === 0) {
            return new LogicException('Missing #[ArrayOf] attribute to specify the item type.');
        }
        if ($nAttributes > 1) {
            return new LogicException('Multiple #[ArrayOf] attributes found; only one is allowed.');
        }
        $arrayOf = $attributes[0]->newInstance();
        $itemSchema = self::inferFromType($arrayOf->itemType);
        if ($itemSchema instanceof Throwable) {
            return $itemSchema;
        }
        return self::array($itemSchema);
    }

    private static function array(self $items): self
    {
        return new self('array', items: $items);
    }

    /**
     * @return array<string, mixed>|true
     */
    #[Override]
    public function jsonSerialize(): array|true
    {
        $data = [];
        if ($this->type !== null) {
            $data['type'] = $this->type;
        }
        if (count($this->properties) !== 0) {
            $data['properties'] = $this->properties;
        }
        if ($this->type === 'object') {
            $data['required'] = $this->required;
        }
        if ($this->additionalProperties !== null) {
            $data['additionalProperties'] = $this->additionalProperties;
        }
        if ($this->const !== null) {
            /** @psalm-suppress MixedAssignment */
            $data['const'] = $this->const;
        }
        if (count($this->enum) !== 0) {
            $data['enum'] = $this->enum;
        }
        if (count($data) === 0) {
            return true;
        }
        if ($this->items !== null) {
            $data['items'] = $this->items;
        }
        return $data;
    }
}
