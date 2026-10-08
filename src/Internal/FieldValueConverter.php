<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use stdClass;

use function class_exists;
use function enum_exists;

/** @internal */
final readonly class FieldValueConverter
{
    private ReflectionType|null $type;
    private string $typeName;

    public function __construct(
        private ReflectionParameter|ReflectionProperty $field,
        private ListType|MapType|TupleType|FieldCollectionUnionType|null $collection,
    ) {
        $this->type = $field->getType();
        $this->typeName = $this->type instanceof ReflectionNamedType
            ? FieldTypeNameResolver::resolve($field, $this->type)
            : (string) $this->type;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws JsonException
     * @throws ReflectionException
     */
    public function convert(string $class, mixed $value, string $path): array|bool|float|int|object|string|null
    {
        if ($this->collection instanceof FieldCollectionUnionType) {
            return FieldCollectionUnionValueConverter::convert($class, $path, $this->collection, $value);
        }
        if ($this->collection !== null) {
            return CollectionValueConverter::convert($class, $path, $this->collection, $value);
        }
        if ($this->type instanceof ReflectionUnionType) {
            return $this->convertUnion($class, $this->type, $value, $path);
        }
        if ($this->type instanceof ReflectionNamedType) {
            return $this->convertNamed($class, $this->type, $value, $path);
        }
        return $value;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    private function convertNamed(
        string $class,
        ReflectionNamedType $type,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        if ($type->isBuiltin()) {
            $matches = ValueTypeMatcher::matches($value, $type);
            if (!$matches) {
                return DecodeError::fieldTypeMismatch(
                    $class,
                    $path,
                    FieldTypeNameResolver::expected($type, $this->typeName),
                    $value,
                );
            }
            return $value;
        }
        if (enum_exists($this->typeName)) {
            return BackedEnumValueConverter::convert($class, $this->field, $value, $path) ?? $value;
        }
        if (class_exists($this->typeName)) {
            return ConcreteClassValueConverter::convert($class, $this->field, $this->typeName, $value, $path);
        }
        return $value;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    private function convertUnion(
        string $class,
        ReflectionUnionType $type,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        $converted = BackedEnumValueConverter::convert($class, $this->field, $value, $path);
        if ($converted !== null) {
            return $converted;
        }
        if ($value instanceof stdClass) {
            $converted = ConcreteClassUnionValueConverter::convert($class, $this->field, $type, $value, $path);
            if ($converted !== null) {
                return $converted;
            }
        }
        $matches = ValueTypeMatcher::matchesBuiltinUnion($value, $type);
        if (!$matches) {
            return DecodeError::fieldTypeMismatch($class, $path, (string) $type, $value);
        }
        return $value;
    }
}
