<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;

use function array_flip;

/** @internal */
final class ConstructorDecoder
{
    /** @var array<class-string, ConstructorPlan|MappedConstructorPlan> */
    private static array $plans = [];

    /**
     * @param class-string $class
     * @phpstan-impure
     */
    public static function cachedPlan(string $class): ConstructorPlan|null
    {
        $plan = self::$plans[$class] ?? null;
        return $plan instanceof ConstructorPlan ? $plan : null;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
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

        $names = RootTypeValidator::fieldNames($class);
        if ($names instanceof DecodeError) {
            return $names;
        }
        $compiler = new TypeCompiler($className);
        $checks = [];
        $converters = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $reflection) {
            $parameter = new ConstructorParameter($reflection, $class);
            $name = $names[$parameter->name] ?? $parameter->name;
            $node = $parameter->resolveType($compiler);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $converters[$name] = $parameter->converter($node);
            $check = $parameter->nativeCheck();
            if ($check !== null) {
                $checks[$name] = $check;
            }
        }

        $plan = new ConstructorPlan($className, $checks, $converters);
        if ($names !== []) {
            $plan = new MappedConstructorPlan($plan, array_flip($names));
        }
        if ($compiler->unresolved === []) {
            self::$plans[$className] = $plan;
        }
        return $plan->decode($values, $path);
    }
}
