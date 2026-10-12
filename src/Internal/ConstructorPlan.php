<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use JsonException;
use ReflectionException;
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

/** @internal */
final readonly class ConstructorPlan
{
    public bool $scalarOnly;

    /** @var array<array-key, string> Native type names of the arguments that need a type check. */
    private array $types;

    /** @var array<array-key, string> The type that errors report for each checked argument that accepts null. */
    private array $nullable;

    /**
     * @param class-string $class
     * @param array<array-key, array{string, string|null}> $checks Native type checks: type name, and the type that
     *     errors report if the argument accepts null.
     * @param array<array-key, Node|null> $converters
     */
    public function __construct(
        private string $class,
        array $checks,
        private array $converters,
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
        $this->scalarOnly = array_all($converters, static fn(Node|null $converter): bool => $converter === null);
    }

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
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
                    $path === '' ? (string) $name : FieldPath::field($path, (string) $name),
                    $this->nullable[$name] ?? $typeName,
                    $value,
                );
            }
        }
        return $this->scalarOnly ? array_intersect_key($values, $this->types) : $this->convert($values, $path);
    }

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     */
    private function convert(array $values, string $path): array|DecodeError
    {
        $converted = [];
        foreach ($this->converters as $name => $converter) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            if ($converter !== null) {
                $fieldPath = $path === '' ? (string) $name : FieldPath::field($path, (string) $name);
                $value = $converter->decode($value, $fieldPath);
                if ($value instanceof DecodeError) {
                    return $value;
                }
            }
            $converted[$name] = $value;
        }
        return $converted;
    }
}
