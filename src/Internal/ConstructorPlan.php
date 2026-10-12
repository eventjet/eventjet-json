<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use JsonException;
use ReflectionException;

use function array_all;
use function array_intersect_key;
use function array_key_exists;

/** @internal */
final readonly class ConstructorPlan
{
    public bool $scalarOnly;

    /**
     * @param class-string $class
     * @param array<array-key, ConstructorValueValidator> $fields
     * @param array<array-key, Node|null> $converters
     */
    public function __construct(
        private string $class,
        private array $fields,
        private array $converters,
    ) {
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
        foreach ($this->fields as $name => $field) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            $matches = ValueTypeMatcher::matches($value, $field->typeName, $field->nullable);
            if (!$matches) {
                return DecodeError::fieldTypeMismatch(
                    $this->class,
                    $path === '' ? (string) $name : FieldPath::field($path, (string) $name),
                    $field->expected,
                    $value,
                );
            }
        }
        return $this->scalarOnly ? array_intersect_key($values, $this->fields) : $this->convert($values, $path);
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
