<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\ConstructorFailure;
use Eventjet\Json\Internal\Error\InvalidValue;
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
     * @param array<array-key, true> $declared The JSON names of the other fields as keys, if a field collects the
     *     other members.
     */
    public function __construct(
        public string $class,
        public array $fields,
        private OtherMembersField|null $others,
        private array $declared,
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
        [$names, $attributed, $others, $declared] = TargetClass::validate($class);
        [$fields, $collector] = ClassField::compile($class, $names, $attributed, $others);
        return new self($class->getName(), $fields, $collector, $declared);
    }

    /**
     * Decodes the members of a JSON object field by field, stopping at the first error, then creates the object: it
     * calls the constructor and assigns the public properties outside it.
     *
     * @throws DecodeError
     * @throws JsonException
     */
    public function decode(stdClass $object, string $path): object
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
                $value = $field->node->decode($value, $path === '' ? $name : FieldPath::field($path, $name));
            }
            if ($field->property === null) {
                $arguments[$field->name] = $value;
                continue;
            }
            $assignments[] = [$field->property, $value];
        }
        if ($this->others !== null) {
            $this->collect($this->others, $values, $path, $arguments, $assignments);
        }
        try {
            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            $created = new $this->class(...$arguments);
        } catch (Throwable $error) {
            $missing = $this->missesArgument($arguments);
            $failure = $missing ? InvalidValue::missingArgument($error) : ConstructorFailure::constructorThrew($error);
            throw $failure;
        }
        foreach ($assignments as [$property, $value]) {
            try {
                $property->setValue($created, $value);
            } catch (Throwable $error) {
                throw ConstructorFailure::assignmentThrew($property, $error);
            }
        }
        return $created;
    }

    /**
     * Adds the members that no declared field claims to the arguments or the assignments.
     *
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values The members of the
     *     JSON object.
     * @param array<string, mixed> $arguments
     * @param list<array{ReflectionProperty, array<array-key, mixed>|bool|float|int|object|string|null}> $assignments
     * @param-out array<string, mixed> $arguments
     * @param-out list<array{ReflectionProperty, array<array-key, mixed>|bool|float|int|object|string|null}> $assignments
     * @throws DecodeError
     */
    private function collect(
        OtherMembersField $others,
        array $values,
        string $path,
        array &$arguments,
        array &$assignments,
    ): void {
        $collected = $others->collect($values, $this->declared, $path);
        if ($others->property === null) {
            $arguments[$others->name] = $collected;
            return;
        }
        $assignments[] = [$others->property, $collected];
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
