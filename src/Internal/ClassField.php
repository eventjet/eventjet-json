<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\JsonValueNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;

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
        $scalar = $node instanceof ScalarNode ? $node : null;
        $this->scalar = $scalar?->name;
        $this->nullable = $scalar?->nullable === true;
    }

    /**
     * Compiles the fields of a class: its constructor parameters, then its public instance properties that the
     * constructor does not set, each in declaration order, and separately the field that collects the other members.
     * Throws for the first declaration that cannot round-trip.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @param array<string, true> $attributed The properties that declare any attribute, keyed by name.
     * @param OtherMembersMapping|null $others The property that collects the other members, if any.
     * @return array{list<self>, OtherMembersField|null}
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function compile(
        ReflectionClass $class,
        array $names,
        array $attributed,
        OtherMembersMapping|null $others,
    ): array {
        $parameters = self::parameters($class);
        $properties = self::properties($class);
        $collector = null;
        if ($others !== null) {
            $collector = OtherMembersField::of($class, $others, $parameters);
            $collecting = $collector->name;
            unset($properties[$collecting]);
            $parameters = array_filter(
                $parameters,
                static fn(ReflectionParameter $parameter): bool => $parameter->getName() !== $collecting,
            );
        }
        $fields = [];
        foreach ($parameters as $parameter) {
            $field = self::parameter($parameter, $names, $properties);
            $fields[] = $field;
            unset($properties[$field->name]);
        }
        foreach ($properties as $name => $property) {
            $fields[] = new self($names[$name] ?? $name, $name, self::node($property, $attributed), $property, false);
        }
        foreach ($fields as $field) {
            if (!$field->node instanceof JsonValueNode) {
                continue;
            }
            $field->node->requireSerializer($class, $field->name);
        }
        return [$fields, $collector];
    }

    /**
     * Returns the fields of a class with their JSON names, without compiling or checking them, in the order that
     * compile() compiles them. Unlike compile(), it includes the field that collects the other members, under its own
     * name. Its array type has no literal values, so it never tells classes apart.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @return list<array{string, ReflectionParameter|ReflectionProperty}>
     */
    public static function uncompiled(ReflectionClass $class, array $names): array
    {
        $parameters = self::parameters($class);
        $fields =
            array_combine(array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            ), $parameters) + self::properties($class);
        return array_map(
            /** @return array{string, ReflectionParameter|ReflectionProperty} */
            static fn(string $name, ReflectionParameter|ReflectionProperty $field): array => [
                $names[$name] ?? $name,
                $field,
            ],
            array_keys($fields),
            $fields,
        );
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<ReflectionParameter>
     */
    private static function parameters(ReflectionClass $class): array
    {
        return $class->getConstructor()?->getParameters() ?? [];
    }

    /**
     * Returns the public instance properties of a class, keyed by name.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<string, ReflectionProperty>
     */
    private static function properties(ReflectionClass $class): array
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
        return $properties;
    }

    /**
     * Compiles a constructor parameter. Its value must be recoverable from a same-named public instance property,
     * because json_encode() writes only those.
     *
     * @param array<string, string> $names
     * @param array<string, ReflectionProperty> $properties The public instance properties of the class, keyed by name.
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function parameter(ReflectionParameter $parameter, array $names, array $properties): self
    {
        $name = $parameter->getName();
        if ($parameter->isVariadic()) {
            throw UnsupportedField::variadic($name);
        }
        $node = self::node($parameter, [$name => true]);
        return array_key_exists($name, $properties)
            ? new self($names[$name] ?? $name, $name, $node, null, !$parameter->isOptional())
            : throw UnsupportedField::withoutProperty($name);
    }

    /**
     * @param array<string, true> $attributed The fields that may declare attributes, keyed by name. A constructor
     *     parameter always may: an ordinary one has attributes of its own, and telling it from a promoted one costs as
     *     much as looking them up.
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function node(ReflectionParameter|ReflectionProperty $field, array $attributed): Node
    {
        $type = $field->getType();
        return $type === null
            ? throw UnsupportedField::untyped($field->getName())
            : new TypeCompiler()->field($field, $type, $attributed);
    }
}
