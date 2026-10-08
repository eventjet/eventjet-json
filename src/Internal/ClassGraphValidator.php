<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function class_exists;

/** @internal */
final class ClassGraphValidator
{
    /** @var array<class-string, true> */
    private array $visited = [];

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public function validate(string $class): DecodeError|null
    {
        $reflection = new ReflectionClass($class);
        $class = $reflection->getName();
        if ($this->visited[$class] ?? false) {
            return null;
        }
        $this->visited[$class] = true;
        $error = RootTypeValidator::validate($reflection);
        if ($error !== null) {
            return $error;
        }

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $resolved = new ConstructorParameter($parameter, $reflection)->resolveType($class);
            $error = $this->field($class, $parameter, $resolved);
            if ($error !== null) {
                return $error;
            }
        }

        $properties = PublicProperties::resolve($reflection);
        if ($properties instanceof DecodeError) {
            return $properties;
        }
        foreach ($properties as $property) {
            $field = $property['property'];
            $resolved = FieldTypeResolver::resolve($class, $field);
            $error = $this->field($class, $field, $resolved);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private function field(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null $resolved,
    ): DecodeError|null {
        if ($resolved instanceof DecodeError) {
            return $resolved;
        }
        foreach (ClassTypeDependencies::field($field, $resolved) as $type) {
            $error = $this->named($class, $field->getName(), $type);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    private function named(string $class, string $field, string $type): DecodeError|null
    {
        if (!class_exists($type)) {
            return DecodeError::nonInstantiableField($class, $field, 'unknown class', $type);
        }
        return $this->validate($type);
    }
}
