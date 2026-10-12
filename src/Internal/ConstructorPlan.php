<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use JsonException;
use stdClass;

use function array_all;
use function array_filter;
use function array_intersect_key;
use function array_key_exists;
use function array_map;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Turns the members of a JSON object into constructor arguments.
 *
 * @internal
 */
final readonly class ConstructorPlan
{
    /** Whether the checked members are the arguments: none needs a converter or has a different JSON name. */
    private bool $scalarOnly;

    /** @var array<array-key, string> Native type names of the members that need a type check, keyed by JSON name. */
    private array $types;

    /** @var array<array-key, string> The type that errors report for each checked member that accepts null. */
    private array $nullable;

    /**
     * @param class-string $class
     * @param array<array-key, array{string, string|null}> $checks Native type checks, keyed by JSON name: type name,
     *     and the type that errors report if the argument accepts null.
     * @param array<string, Node|null> $converters Converter of each argument, keyed by parameter name. The converter
     *     is null if the argument needs only its native type check.
     * @param array<string, string> $jsonNames JSON names of the arguments whose JSON name differs from their parameter
     *     name, keyed by parameter name.
     */
    public function __construct(
        private string $class,
        array $checks,
        public array $converters,
        private array $jsonNames,
    ) {
        $this->types = array_map(
            /** @param array{string, string|null} $check */
            static fn(array $check): string => $check[0],
            $checks,
        );
        $this->nullable = array_filter(
            array_map(
                /** @param array{string, string|null} $check */
                static fn(array $check): string|null => $check[1],
                $checks,
            ),
            static fn(string|null $type): bool => $type !== null,
        );
        $this->scalarOnly =
            $jsonNames === [] && array_all($converters, static fn(Node|null $converter): bool => $converter === null);
    }

    /**
     * Checks the native types of all present members before converting any of them.
     *
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    public function decode(array $values, string $path): array|DecodeError
    {
        foreach ($this->types as $name => $typeName) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            // Inline checks, because a call per argument slows down hydration measurably.
            $matches = $value === null
                ? array_key_exists($name, $this->nullable)
                : match ($typeName) {
                    'array' => is_array($value) || $value instanceof stdClass,
                    'bool' => is_bool($value),
                    'false' => $value === false,
                    'float' => is_float($value) || is_int($value),
                    'int' => is_int($value),
                    'null' => false,
                    'string' => is_string($value),
                    'true' => $value === true,
                    default => true,
                };
            if (!$matches) {
                return DecodeError::fieldTypeMismatch(
                    $this->class,
                    FieldPath::field($path, (string) $name),
                    $this->nullable[$name] ?? $typeName,
                    $value,
                );
            }
        }
        return $this->scalarOnly ? array_intersect_key($values, $this->types) : $this->convert($values, $path);
    }

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    private function convert(array $values, string $path): array|DecodeError
    {
        if ($this->jsonNames !== []) {
            return RenamedArguments::convert($this->converters, $this->jsonNames, $values, $path);
        }
        $arguments = [];
        // Each parameter name is also the JSON name, which spares a lookup per argument.
        foreach ($this->converters as $name => $converter) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            if ($converter !== null) {
                $fieldPath = $path === '' ? $name : FieldPath::field($path, $name);
                $value = $converter->decode($value, $fieldPath);
                if ($value instanceof DecodeError) {
                    return $value;
                }
            }
            $arguments[$name] = $value;
        }
        return $arguments;
    }
}
