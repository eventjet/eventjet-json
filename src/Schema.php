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
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use UnitEnum;

use function array_unshift;
use function assert;
use function class_exists;
use function count;
use function get_object_vars;
use function is_a;
use function sprintf;

/**
 * This class isn't `final` on purpose so it can be extended in order to be used in attributes.
 *
 * @api
 */
readonly class Schema implements JsonSerializable
{
    /**
     * @param 'string'|'number'|'object'|'array'|'boolean'|'null'|'integer'|null $type
     * @param array<string, self> $properties
     * @param list<string> $required
     * @param list<string|int> $enum
     * @param list<self> $anyOf
     */
    protected function __construct(
        public string|null $type = null,
        public array $properties = [],
        public array $required = [],
        public bool|null $additionalProperties = null,
        public mixed $const = null,
        public array $enum = [],
        public self|null $items = null,
        public array $anyOf = [],
        public string|null $format = null,
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

    public static function string(): self
    {
        return new self('string');
    }

    public static function integer(): self
    {
        return new self('integer');
    }

    public static function number(): self
    {
        return new self('number');
    }

    public static function boolean(): self
    {
        return new self('boolean');
    }

    public static function const(mixed $value): self
    {
        return new self(const: $value);
    }

    public static function null(): self
    {
        return new self('null');
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
                $parameterSchema = self::inferFromReflectionParameter($parameter);
                if ($parameterSchema instanceof Throwable) {
                    return new LogicException(
                        sprintf(
                            'Failed to infer schema for %s::%s: %s',
                            $type,
                            $parameter->getName(),
                            $parameterSchema->getMessage(),
                        ),
                        previous: $parameterSchema,
                    );
                }
                $format = self::getOptionalAttribute($parameter, Format::class)?->format;
                if ($format !== null) {
                    $parameterSchema = $parameterSchema->withFormat($format);
                }
                $properties[$parameter->getName()] = $parameterSchema;
                if (!$parameter->isOptional()) {
                    $required[] = $parameter->getName();
                }
            }
        }
        return new self('object', $properties, $required, additionalProperties: false);
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

    private static function inferFromReflectionParameter(ReflectionParameter $parameter): self|Throwable
    {
        $paramType = $parameter->getType();
        if ($paramType === null) {
            return new LogicException('Missing type. If you want it to accept anything, use "mixed" as type.');
        }
        if ($paramType instanceof ReflectionNamedType && $paramType->getName() === 'array') {
            $schema = self::inferFromArrayParameter($parameter);
            if ($schema instanceof Throwable) {
                return $schema;
            }
            return $schema;
        }
        return self::inferFromReflectionType($paramType);
    }

    private static function inferFromReflectionType(ReflectionType $type): self|Throwable
    {
        if ($type instanceof ReflectionUnionType) {
            $schemas = [];
            foreach ($type->getTypes() as $unionType) {
                $schema = self::inferFromReflectionType($unionType);
                if ($schema instanceof Throwable) {
                    return $schema;
                }
                $schemas[] = $schema;
            }
            return self::anyOf(...$schemas);
        }
        if (!$type instanceof ReflectionNamedType) {
            return new LogicException(sprintf(
                'Intersections are not supported: %s.',
                (string)$type,
            ));
        }
        $propertySchema = self::inferFromType($type->getName());
        if ($propertySchema instanceof Throwable) {
            return $propertySchema;
        }
        return $propertySchema;
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
     * @no-named-arguments
     */
    private static function anyOf(self $schema, self ...$schemas): self
    {
        array_unshift($schemas, $schema);
        return new self(anyOf: $schemas);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    private static function getOptionalAttribute(ReflectionParameter $attributeTarget, string $class): object|null
    {
        $attributes = $attributeTarget->getAttributes($class);
        $n = count($attributes);
        if ($n === 0) {
            return null;
        }
        if ($n > 1) {
            throw new LogicException(sprintf(
                'Multiple attributes of type %s found; only one is allowed.',
                $class,
            ));
        }
        $attribute = $attributes[0]->newInstance();
        assert($attribute instanceof $class);
        return $attribute;
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
        if ($this->items !== null) {
            $data['items'] = $this->items;
        }
        if (count($this->anyOf) !== 0) {
            $data['anyOf'] = $this->anyOf;
        }
        if ($this->format !== null) {
            $data['format'] = $this->format;
        }
        if (count($data) === 0) {
            return true;
        }
        return $data;
    }

    public function withFormat(string $format): self
    {
        $data = get_object_vars($this);
        $data['format'] = $format;
        /** @phpstan-ignore-next-line argument.type */
        return new self(...$data);
    }
}
