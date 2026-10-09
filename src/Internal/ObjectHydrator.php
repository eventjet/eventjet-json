<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;
use Throwable;

use function get_object_vars;

/** @internal */
final class ObjectHydrator
{
    /** @var array<class-string, ReflectionClass<object>|ConstructorPlan> */
    private static array $validatedClasses = [];
    /** @var array<class-string, DirectScalarPlan|DirectListPlan|bool> */
    public static array $directPlans = [];

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function hydrate(string $class, stdClass $object, string $path = ''): object
    {
        try {
            /** @var ReflectionClass<T>|ConstructorPlan|null $reflection */
            $reflection = self::$validatedClasses[$class] ?? null;
            /** @var array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values */
            $values = get_object_vars($object);
            $assignments = [];
            $convertedValues = $reflection instanceof ConstructorPlan
                ? $reflection->decode($values, $path)
                : self::prepare($class, $reflection, $values, $path, $assignments);
            if ($convertedValues instanceof DecodeError) {
                return $convertedValues;
            }
            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            $object = new $class(...$convertedValues);
            if ($assignments !== []) {
                PublicPropertyHydrator::assign($object, $assignments);
            }

            return $object;
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param ReflectionClass<T>|null $reflection
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @param list<array{property: ReflectionProperty, value: mixed}> $assignments
     * @param-out list<array{property: ReflectionProperty, value: mixed}> $assignments
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function prepare(
        string $class,
        ReflectionClass|null $reflection,
        array $values,
        string $path,
        array &$assignments,
    ): array|DecodeError {
        $uncached = $reflection === null;
        if ($reflection === null) {
            $reflection = new ReflectionClass($class);
            $targetError = RootTypeValidator::validate($reflection);
            if ($targetError !== null) {
                return $targetError;
            }
            self::$validatedClasses[$class] = $reflection;
        }
        $convertedValues = ConstructorDecoder::convert($reflection, $values, $path);
        if ($convertedValues instanceof DecodeError) {
            return $convertedValues;
        }
        $properties = PublicProperties::resolve($reflection);
        $prepared =
            $properties === [] || $properties instanceof DecodeError
                ? $properties
                : PublicPropertyHydrator::prepare($class, $properties, $values, $path);
        if ($prepared instanceof DecodeError) {
            return $prepared;
        }
        if ($uncached && $properties === []) {
            self::cacheConstructorPlan($class);
        }
        $assignments = $prepared;
        return $convertedValues;
    }

    /** @param class-string $class */
    private static function cacheConstructorPlan(string $class): void
    {
        $plan = ConstructorDecoder::cachedPlan($class);
        if ($plan !== null) {
            self::$validatedClasses[$class] = $plan;
            self::$directPlans[$class] = $plan->directCandidate;
        }
    }
}
