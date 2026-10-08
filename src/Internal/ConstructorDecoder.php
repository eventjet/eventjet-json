<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;

/** @internal */
final class ConstructorDecoder
{
    /** @var array<class-string, ConstructorPlan|MappedConstructorPlan> */
    private static array $plans = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values
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
            $error = $plan->validate($values, $path);
            return $error ?? $plan->convert($values, $path);
        }

        $plan = ConstructorPlanBuilder::build($class, $values, $path);
        if ($plan instanceof DecodeError) {
            return $plan;
        }
        if ($plan->cacheable) {
            self::$plans[$className] = $plan;
        }
        return $plan->convert($values, $path);
    }
}
