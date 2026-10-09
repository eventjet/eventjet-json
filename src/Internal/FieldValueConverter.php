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

use function assert;
use function class_exists;
use function enum_exists;

/** @internal */
final readonly class FieldValueConverter
{
    private ReflectionType|null $type;
    private string $typeName;
    private EnumUnionLookup|null $enumLookup;
    /** @var enum-string|null */
    private string|null $enumName;

    /** @throws ReflectionException */
    public function __construct(
        private ReflectionParameter|ReflectionProperty $field,
        public ListType|MapType|TupleType|CollectionUnionType|null $collection,
    ) {
        $this->type = $field->getType();
        $this->enumLookup = $this->type instanceof ReflectionUnionType ? new EnumUnionLookup($this->type) : null;
        $typeName = $this->type instanceof ReflectionNamedType
            ? FieldTypeNameResolver::resolve($field, $this->type)
            : (string) $this->type;
        $this->typeName = $typeName;
        // Builtin names would reach autoloaders.
        $this->enumName = $this->type instanceof ReflectionNamedType
        && !$this->type->isBuiltin()
        && enum_exists($typeName)
            ? $typeName
            : null;
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
        if ($this->collection instanceof CollectionUnionType) {
            return CollectionUnionValueConverter::convert($class, $path, $this->collection, $value);
        }
        if ($this->collection !== null) {
            return CollectionValueConverter::convert($class, $path, $this->collection, $value);
        }
        if ($this->type instanceof ReflectionUnionType) {
            assert($this->enumLookup !== null, description: 'Union fields have a prepared union lookup.');
            return $this->enumLookup->convertField($class, $this->field, $this->type, $value, $path);
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
        if ($this->enumName !== null) {
            if ($value === null && $type->allowsNull()) {
                return null;
            }
            return BackedEnumValueConverter::convertValue($class, $path, $this->enumName, $value, $type);
        }
        if (class_exists($this->typeName)) {
            return ConcreteClassValueConverter::convert($class, $this->field, $this->typeName, $value, $path);
        }
        return $value;
    }
}
