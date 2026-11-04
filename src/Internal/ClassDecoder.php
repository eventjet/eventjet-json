<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\ConstructorFailure;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\JsonValue;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;
use Throwable;

use function array_all;
use function array_any;
use function array_key_exists;
use function class_exists;
use function get_object_vars;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * The compiled decoder of one class: its constructor parameters and its public properties outside the constructor.
 *
 * @internal
 */
final readonly class ClassDecoder
{
    /** Whether every referenced class was loaded, so the decoder does not need to be compiled again. */
    public bool $complete;

    /**
     * @param class-string $class
     * @param list<ClassField> $fields The constructor parameters, then the public properties outside the constructor.
     * @param OtherMembersField|null $others The field that collects the members that no other field claims, if any.
     */
    public function __construct(
        public string $class,
        public array $fields,
        private OtherMembersField|null $others,
    ) {
        $this->complete = array_all($fields, static fn(ClassField $field): bool => array_all(
            $field->node->dependencies(),
            static fn(string $dependency): bool => class_exists($dependency),
        ));
    }

    /**
     * Compiles the decoder of a class, rejecting declarations that cannot round-trip.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function compile(ReflectionClass $class): self
    {
        [$names, $attributed, $others] = TargetClass::validate($class);
        [$fields, $collector] = ClassField::compile($class, $names, $attributed, $others);
        return new self($class->getName(), $fields, $collector);
    }

    /**
     * Decodes the members of a JSON object field by field, stopping at the first error, then creates the object: it
     * calls the constructor and assigns the public properties outside it. Returns the error instead of throwing it,
     * because each throw makes rejected documents measurably slower.
     *
     * @return object|DecodeError
     * @throws JsonException
     */
    public function decode(stdClass $object): object
    {
        /** @var array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values */
        $values = get_object_vars($object);
        $arguments = [];
        $assignments = [];
        foreach ($this->fields as $field) {
            $name = $field->json;
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            // Values that already have the declared scalar type skip the node. The node checks and converts all others.
            $unchanged = $value === null
                ? $field->nullable
                : match ($field->scalar) {
                    'bool' => is_bool($value),
                    'float' => is_float($value),
                    'int' => is_int($value),
                    'string' => is_string($value),
                    default => false,
                };
            if (!$unchanged) {
                try {
                    $value = $field->node->decode($value);
                } catch (DecodeError $error) {
                    return $error->inMember($name);
                }
            }
            if ($field->property === null) {
                $arguments[$field->name] = $value;
                continue;
            }
            $assignments[] = [$field->property, $value];
        }
        if ($this->others !== null) {
            [$arguments, $assignments] = self::withOthers(
                $this->others,
                $this->others->collect($values, $path),
                $arguments,
                $assignments,
            );
        }
        try {
            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            $created = new $this->class(...$arguments);
        } catch (Throwable $error) {
            $missing = $this->missesArgument($arguments);
            return $missing ? InvalidValue::missingArgument($error) : ConstructorFailure::constructorThrew($error);
        }
        foreach ($assignments as [$property, $value]) {
            try {
                $property->setValue($created, $value);
            } catch (Throwable $error) {
                return ConstructorFailure::assignmentThrew($property, $error);
            }
        }
        return $created;
    }

    /**
     * Adds the collected other members to the arguments or the assignments, like a declared field.
     *
     * @param array<array-key, JsonValue> $collected
     * @param array<string, mixed> $arguments
     * @param list<array{ReflectionProperty, array<array-key, mixed>|bool|float|int|object|string|null}> $assignments
     * @return array{array<string, mixed>, list<array{ReflectionProperty, array<array-key, mixed>|bool|float|int|object|string|null}>}
     */
    private static function withOthers(
        OtherMembersField $others,
        array $collected,
        array $arguments,
        array $assignments,
    ): array {
        if ($others->property === null) {
            $arguments[$others->name] = $collected;
            return [$arguments, $assignments];
        }
        $assignments[] = [$others->property, $collected];
        return [$arguments, $assignments];
    }

    /**
     * Whether a parameter without a default value has no argument, so that PHP rejected the call before the constructor
     * ran.
     *
     * @param array<string, mixed> $arguments Keyed by parameter name.
     */
    private function missesArgument(array $arguments): bool
    {
        return array_any(
            $this->fields,
            static fn(ClassField $field): bool => $field->required && !array_key_exists($field->name, $arguments),
        );
    }
}
