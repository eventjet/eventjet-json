<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;

/** @internal */
final class ConstructorDecoder
{
    /** @var array<class-string, ConstructorPlan> */
    private static array $plans = [];

    /**
     * @param class-string $class
     * @phpstan-impure
     */
    public static function scalarPlan(string $class): ConstructorPlan|null
    {
        $plan = self::$plans[$class] ?? null;
        return $plan?->scalarOnly === true ? $plan : null;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<string, mixed>|DecodeError
     * @phpstan-impure
     * @throws ReflectionException
     * @throws JsonException
     */
    public static function convert(ReflectionClass $class, array $values, string $path): array|DecodeError
    {
        $className = $class->getName();
        $plan = self::$plans[$className] ?? null;
        if ($plan !== null) {
            return $plan->decode($values, $path);
        }

        $fields = [];
        $cacheable = true;
        $converters = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $reflection) {
            $parameter = new ConstructorParameter($reflection, $class);
            $name = $parameter->name;
            $resolved = $parameter->resolveType($className);
            $cacheable = $cacheable && $resolved !== null;
            $collection = $resolved === false ? null : $resolved;

            if ($collection instanceof DecodeError) {
                return $collection;
            }

            $converters[$name] = $parameter->builtin && $collection === null
                ? null
                : new FieldValueConverter($reflection, $collection);
            $field = ConstructorValueValidator::forParameter($parameter, $values);
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

        $plan = new ConstructorPlan($className, $fields, $converters);
        if ($cacheable) {
            self::$plans[$className] = $plan;
        }
        return $plan->convert($values, $path);
    }
}
