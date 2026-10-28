<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;

/**
 * A member of the JSON object that a class decoder reads: a constructor parameter, or a public property outside the
 * constructor.
 *
 * @internal
 */
final readonly class ClassField
{
    /**
     * The type of a scalar field, or null for other fields. The class decoder checks scalar values itself, because a
     * node call per field slows down hydration measurably.
     */
    public string|null $scalar;

    /** Whether the field is a scalar field that accepts null. */
    public bool $nullable;

    /**
     * @param string $json The name of the JSON member.
     * @param string $name The name of the constructor parameter or the property.
     * @param ReflectionProperty|null $property The property that decoding assigns after construction, or null for a
     *     constructor argument.
     * @param bool $required Whether the field is a constructor parameter without a default value.
     */
    private function __construct(
        public string $json,
        public string $name,
        public Node $node,
        public ReflectionProperty|null $property,
        public bool $required,
    ) {
        $this->scalar = $node instanceof ScalarNode ? $node->name : null;
        $this->nullable = $node instanceof ScalarNode && $node->nullable;
    }

    /**
     * Compiles the fields of a class: its constructor parameters, then its public instance properties that the
     * constructor does not set, each in declaration order. Returns the first declaration that cannot round-trip.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @return list<self>|DecodeError
     * @throws ReflectionException
     */
    public static function compile(ReflectionClass $class, array $names): array|DecodeError
    {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            $publicInstance =
                ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
                === ReflectionProperty::IS_PUBLIC;
            if (!$publicInstance) {
                continue;
            }
            $properties[$property->getName()] = $property;
        }
        $fields = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = self::parameter($parameter, $names, $properties);
            if ($field instanceof DecodeError) {
                return $field;
            }
            $fields[] = $field;
            unset($properties[$field->name]);
        }
        foreach ($properties as $name => $property) {
            $node = self::node($property);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $fields[] = new self($names[$name] ?? $name, $name, $node, $property, false);
        }
        return $fields;
    }

    /**
     * Compiles a constructor parameter. Its value must be recoverable from a same-named public instance property,
     * because json_encode() writes only those.
     *
     * @param array<string, string> $names
     * @param array<string, ReflectionProperty> $properties The public instance properties of the class, keyed by name.
     * @throws ReflectionException
     */
    private static function parameter(ReflectionParameter $parameter, array $names, array $properties): self|DecodeError
    {
        $name = $parameter->getName();
        if ($parameter->isVariadic()) {
            return UnsupportedField::variadic($name);
        }
        $node = self::node($parameter);
        if ($node instanceof DecodeError) {
            return $node;
        }
        return array_key_exists($name, $properties)
            ? new self($names[$name] ?? $name, $name, $node, null, !$parameter->isOptional())
            : UnsupportedField::withoutProperty($name);
    }

    /** @throws ReflectionException */
    private static function node(ReflectionParameter|ReflectionProperty $field): Node|DecodeError
    {
        $type = $field->getType();
        return $type === null ? UnsupportedField::untyped($field->getName()) : new TypeCompiler()->field($field, $type);
    }
}
