<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;

/** @internal */
final class ConstructorPlanBuilder
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @throws ReflectionException
     */
    public static function build(
        ReflectionClass $class,
        array $values,
        string $path,
    ): ConstructorPlan|MappedConstructorPlan|DecodeError {
        $className = $class->getName();
        $names = FieldNames::resolve($class);
        if ($names instanceof DecodeError) {
            return $names;
        }
        $argumentNames = [];
        $fields = [];
        $cacheable = true;
        $converters = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $reflection) {
            $parameter = new ConstructorParameter($reflection, $class);
            $name = $names[$parameter->name] ?? $parameter->name;
            if ($name !== $parameter->name) {
                $argumentNames[$name] = $parameter->name;
            }
            $resolved = $parameter->resolveType($className);
            $cacheable = $cacheable && $resolved !== null;
            $collection = $resolved === false ? null : $resolved;

            if ($collection instanceof DecodeError) {
                return $collection;
            }

            $converters[$name] = $parameter->builtin && $collection === null
                ? null
                : new FieldValueConverter($reflection, $collection);
            $field = ConstructorValueValidator::forParameter($parameter, $values, $name);
            if ($field !== null) {
                $fields[$name] = $field;
                if (array_key_exists($name, $values)) {
                    $error = $field->validate($className, $name, $values[$name], $path);
                    if ($error !== null) {
                        return $error;
                    }
                }
            }
        }

        $plan = new ConstructorPlan($className, $fields, $converters, $cacheable);
        return $argumentNames === [] ? $plan : new MappedConstructorPlan($plan, $argumentNames);
    }
}
