<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

use function array_key_exists;

/** @internal */
final readonly class ConstructorPlan
{
    /**
     * @param class-string $class
     * @param array<string, ConstructorValueValidator> $fields
     * @param array<string, FieldValueConverter|null> $converters
     */
    public function __construct(
        private string $class,
        private array $fields,
        private array $converters,
    ) {}

    /**
     * @param array<array-key, mixed> $values
     */
    public function validate(array $values, string $path): DecodeError|null
    {
        foreach ($this->fields as $name => $field) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $error = $field->validate($this->class, $name, $values[$name], $path);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, mixed>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public function convert(array $values, string $path): array|DecodeError
    {
        $converted = [];
        foreach ($this->converters as $name => $converter) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $value = $values[$name];
            if ($converter !== null) {
                $value = $converter->convert($this->class, $value, FieldPath::field($path, $name));
                if ($value instanceof DecodeError) {
                    return $value;
                }
            }
            $converted[$name] = $value;
        }
        return $converted;
    }
}
