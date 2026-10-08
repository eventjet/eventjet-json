<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;

use function array_key_exists;

/** @internal */
final readonly class ConstructorValidationPlan
{
    /**
     * @param class-string $class
     * @param array<string, ConstructorValueValidator> $fields
     * @param array<string, ListType|MapType|TupleType|CollectionUnionType|null> $collections
     */
    public function __construct(
        private string $class,
        private array $fields,
        private array $collections,
    ) {}

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, ListType|MapType|TupleType|CollectionUnionType|null>|DecodeError
     */
    public function validate(array $values, string $path): array|DecodeError
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
        return $this->collections;
    }
}
