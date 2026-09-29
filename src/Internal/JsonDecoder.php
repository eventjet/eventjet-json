<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Closure;
use Eventjet\Json\Field;
use Eventjet\Json\JsonError;
use ReflectionClass;
use ReflectionEnum;
use ReflectionObject;
use ReflectionProperty;
use stdClass;

use function array_key_exists;
use function array_keys;
use function array_map;
use function class_exists;
use function count;
use function enum_exists;
use function gettype;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function is_subclass_of;
use function sprintf;

/**
 * @internal
 */
final class JsonDecoder
{
    /**
     * @template T of object
     * @param class-string<T> $target
     * @return T
     */
    public static function object(JsonNode $node, string $target): object
    {
        return self::objectPlan($node, $target)();
    }

    public static function populate(JsonNode $node, object $object): void
    {
        if (!$node->object || !is_array($node->value)) {
            throw JsonError::decodeFailed(sprintf('Expected JSON object, got %s', gettype($node->native())));
        }
        self::applyProperties($object, self::propertyPlans(new ReflectionClass($object), $node->value));
        self::remember($object, $node->value);
    }

    public static function key(ReflectionProperty $property): string
    {
        return ($property->getAttributes(Field::class)[0] ?? null)?->newInstance()->name ?? $property->getName();
    }

    /** @return array<array-key, mixed>|object|string|int|float|bool|null */
    public static function value(JsonNode $node, PhpType $type, string $subject): array|object|string|int|float|bool|null
    {
        return self::plan($node, $type, $subject)();
    }

    /**
     * @template T of object
     * @param class-string<T> $target
     * @return Closure(): T
     */
    private static function objectPlan(JsonNode $node, string $target): Closure
    {
        if (!$node->object || !is_array($node->value)) {
            throw JsonError::decodeFailed(sprintf('Expected JSON object, got %s', gettype($node->native())));
        }
        $class = new ReflectionClass($target);
        $remaining = $node->value;
        $arguments = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $key = $class->hasProperty($name) ? self::key($class->getProperty($name)) : $name;
            if (!array_key_exists($key, $remaining)) {
                if ($parameter->isOptional()) {
                    continue;
                }
                throw JsonError::decodeFailed(sprintf('Missing required constructor argument "%s"', $name));
            }
            $arguments[$name] = self::plan(
                $remaining[$key],
                PhpType::parameter($parameter),
                sprintf('parameter "%s" of class %s', $name, $target),
            );
            unset($remaining[$key]);
        }
        $properties = self::propertyPlans($class, $remaining);
        return static function () use ($class, $arguments, $properties, $node): object {
            $values = array_map(static fn(Closure $argument): mixed => $argument(), $arguments);
            $object = $class->newInstanceArgs($values);
            self::applyProperties($object, $properties);
            self::remember($object, $node->value);
            return $object;
        };
    }

    /** @return Closure(): (array<array-key, mixed>|object|string|int|float|bool|null) */
    private static function plan(JsonNode $node, PhpType $type, string $subject): Closure
    {
        if ($type->name === 'union') {
            return self::union($node, $type, $subject);
        }
        if ($type->name === 'mixed') {
            return static fn(): array|object|string|int|float|bool|null => $node->native();
        }
        if ($type->collection !== null) {
            return self::array($node, $type->collection, $subject);
        }
        if ($type->name === 'intersection') {
            throw JsonError::decodeFailed('Intersection types are not supported');
        }
        if (enum_exists($type->name)) {
            $case = self::enum($node, $type->name, $subject);
            return static fn(): BackedEnum => $case;
        }
        if (class_exists($type->name)) {
            return self::objectPlan($node, $type->name);
        }
        $value = $node->value;
        $valid = $type->acceptsScalar($value)
            ?? throw JsonError::decodeFailed(sprintf('Unsupported type "%s" for %s', $type->name, $subject));
        if (!$valid) {
            throw JsonError::decodeFailed(sprintf('Expected %s for %s, got %s', $type->name, $subject, gettype($node->native())));
        }
        return static fn(): array|string|int|float|bool|null => $type->name === 'float' && is_int($value) ? (float)$value : $value;
    }

    /**
     * @param ReflectionClass<object> $class
     * @param array<array-key, JsonNode> $members
     * @return array<array-key, Closure(): mixed>
     */
    private static function propertyPlans(ReflectionClass $class, array $members): array
    {
        $plans = [];
        if ($class->getName() === stdClass::class) {
            foreach ($members as $key => $member) {
                $plans[$key] = static fn(): mixed => $member->native();
            }
            return $plans;
        }
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $key = self::key($property);
            if (!array_key_exists($key, $members)) {
                continue;
            }
            if ($property->getType() === null && $members[$key]->object) {
                throw JsonError::decodeFailed(sprintf('Property "%s" has no type', $property->getName()));
            }
            $subject = sprintf('property "%s" of class %s', $property->getName(), $class->getName());
            $plans[$property->getName()] = self::plan($members[$key], PhpType::property($property), $subject);
        }
        return $plans;
    }

    /** @param array<array-key, Closure(): mixed> $plans */
    private static function applyProperties(object $object, array $plans): void
    {
        $properties = [];
        foreach (array_keys($plans) as $name) {
            if ($object instanceof stdClass) {
                continue;
            }
            $property = new ReflectionProperty($object, (string)$name);
            if ($property->isInitialized($object) && $property->isReadOnly()) {
                throw JsonError::decodeFailed(sprintf('Cannot populate readonly property "%s"', $name));
            }
            $properties[$name] = $property;
        }
        $values = array_map(static fn(Closure $plan): mixed => $plan(), $plans);
        /** @var mixed $value */
        foreach ($values as $name => $value) {
            if ($object instanceof stdClass) {
                $object->{(string)$name} = $value;
                continue;
            }
            $properties[$name]->setValue($object, $value);
        }
    }

    /**
     * @param array<array-key, JsonNode> $members
     */
    private static function remember(object $object, array $members): void
    {
        $defaults = [];
        $unknown = $members;
        foreach ((new ReflectionObject($object))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $key = self::key($property);
            unset($unknown[$key]);
            if (array_key_exists($key, $members) || !$property->isInitialized($object)) {
                continue;
            }
            /** @var mixed $value */
            $value = $property->getValue($object);
            $defaults[$property->getName()] = ValueSnapshot::capture($value);
        }
        DecodedObject::registry()[$object] = new DecodedObject($members, $unknown, $defaults);
    }

    /** @return Closure(): (array<array-key, mixed>|object|string|int|float|bool|null) */
    private static function union(JsonNode $node, PhpType $type, string $subject): Closure
    {
        $values = [];
        $errors = [];
        foreach ($type->arguments as $option) {
            if ($option->name === 'mixed' || ($option->name === 'null' && $node->value !== null)) {
                continue;
            }
            try {
                $value = self::plan($node, $option, $subject);
                if (is_int($node->value) && $option->name === 'int') {
                    return $value;
                }
                $values[] = $value;
            } catch (JsonError $error) {
                $errors[] = $error;
                continue;
            }
        }
        if (count($values) === 1) {
            return $values[0];
        }
        if ($values !== []) {
            throw JsonError::decodeFailed(sprintf('Ambiguous union type for %s', $subject));
        }
        foreach ($type->arguments as $option) {
            if ($option->name === 'mixed') {
                return static fn(): array|object|string|int|float|bool|null => $node->native();
            }
        }
        if (count($errors) === 1) {
            throw $errors[0];
        }
        throw JsonError::decodeFailed(sprintf('No union member accepts the value for %s', $subject));
    }

    /**
     * @return Closure(): array<array-key, mixed>
     */
    private static function array(JsonNode $node, CollectionType $type, string $subject): Closure
    {
        if (!is_array($node->value)) {
            throw JsonError::decodeFailed(sprintf('Expected array for %s, got %s', $subject, gettype($node->native())));
        }
        if (!$type->acceptsShape($node->object)) {
            throw JsonError::decodeFailed(sprintf('Expected a JSON %s for %s, got %s', $type->object === true ? 'object' : 'array', $subject, $node->object ? 'object' : 'array'));
        }
        $values = [];
        foreach ($node->value as $key => $item) {
            $values[$key] = self::plan($item, $type->item, $subject . sprintf(' at key "%s"', $key));
        }
        return static fn(): array => array_map(static fn(Closure $item): mixed => $item(), $values);
    }

    private static function enum(JsonNode $node, string $class, string $subject): BackedEnum
    {
        if (!is_subclass_of($class, BackedEnum::class)) {
            throw JsonError::decodeFailed(sprintf('Only backed enums are allowed as constructor arguments, but "%s" is not backed', $class));
        }
        $value = $node->value;
        if (!is_string($value) && !is_int($value)) {
            throw JsonError::decodeFailed(sprintf('Expected string or int for %s, got %s', $subject, gettype($value)));
        }
        $backing = (string)(new ReflectionEnum($class))->getBackingType();
        if ((new PhpType($backing))->acceptsScalar($value) !== true) {
            throw JsonError::decodeFailed(sprintf('Value does not match the backing type of enum %s for %s', $class, $subject));
        }
        $case = $class::tryFrom($value);
        if ($case === null) {
            throw JsonError::decodeFailed(sprintf(
                '"%s" is not a valid value for enum %s. Valid values are: %s',
                $value,
                $class,
                implode(', ', array_map(static fn(BackedEnum $case): string|int => $case->value, $class::cases())),
            ));
        }
        return $case;
    }
}
