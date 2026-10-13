<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

use function array_all;
use function array_filter;
use function assert;
use function class_exists;
use function is_array;

/**
 * The compiled decoder of one class: its constructor arguments and its public properties outside the constructor.
 *
 * @internal
 */
final readonly class ClassDecoder
{
    /** Whether every referenced class was loaded, so the decoder does not need to be compiled again. */
    public bool $complete;

    /**
     * @param class-string $class
     * @param array<array-key, array{property: ReflectionProperty, node: Node}> $properties Keyed by JSON name.
     */
    public function __construct(
        public string $class,
        public ConstructorPlan $constructor,
        public array $properties,
    ) {
        $this->complete = array_all($this->fields(), static fn(Node $node): bool => array_all($node->dependencies(), static fn(string $dependency): bool => class_exists(
            $dependency,
        )));
    }

    /**
     * Compiles the decoder of a class, rejecting declarations that cannot round-trip.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws ReflectionException
     */
    public static function compile(ReflectionClass $class): self|DecodeError
    {
        $error = TargetClass::validate($class);
        if ($error !== null) {
            return $error;
        }
        $names = TargetClass::fieldNames($class);
        assert(is_array($names), description: 'The target validation rejects invalid field names.');
        $constructor = self::constructor($class, $names);
        if ($constructor instanceof DecodeError) {
            return $constructor;
        }
        $properties = PublicProperties::compile($class, $names);
        return $properties instanceof DecodeError
            ? $properties
            : new self($class->getName(), $constructor, $properties);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, string> $names JSON names of renamed properties, keyed by property name.
     * @throws ReflectionException
     */
    private static function constructor(ReflectionClass $class, array $names): ConstructorPlan|DecodeError
    {
        $checks = [];
        $converters = [];
        $jsonNames = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $reflection) {
            $parameter = new ConstructorParameter($reflection, $class);
            $name = $names[$parameter->name] ?? $parameter->name;
            $converter = $parameter->resolveType($parameter->plain ? null : new TypeCompiler($class->getName()));
            if ($converter instanceof DecodeError) {
                return $converter;
            }
            $converters[$parameter->name] = $converter;
            if ($name !== $parameter->name) {
                $jsonNames[$parameter->name] = $name;
            }
            $check = $parameter->nativeCheck();
            if ($check !== null) {
                $checks[$name] = $check;
            }
        }
        return new ConstructorPlan($class->getName(), $checks, $converters, $jsonNames);
    }

    /**
     * Returns the nodes of all fields that have one, keyed by property name.
     *
     * @return array<string, Node>
     */
    public function fields(): array
    {
        $fields = array_filter($this->constructor->converters, static fn(Node|null $node): bool => $node !== null);
        foreach ($this->properties as $property) {
            $fields[$property['property']->getName()] = $property['node'];
        }
        return $fields;
    }
}
