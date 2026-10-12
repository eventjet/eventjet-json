<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use stdClass;
use Throwable;

use function array_filter;
use function array_key_exists;
use function get_object_vars;

/** @internal */
final class ObjectHydrator
{
    /** @var array<class-string, ReflectionClass<object>|ConstructorPlan> */
    private static array $validatedClasses = [];

    /** @var array<class-string, true> */
    private static array $scalarPlans = [];

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
            if (array_key_exists($class, self::$scalarPlans)) {
                $result = CompiledHydrators::hydrate($class, $object);
                if ($result !== null) {
                    /** @var T $result */
                    return $result;
                }
            }
            /** @var array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values */
            $values = get_object_vars($object);
            if ($reflection === null) {
                $reflection = new ReflectionClass($class);
                $targetError = RootTypeValidator::validate($reflection);
                if ($targetError !== null) {
                    return $targetError;
                }
                self::$validatedClasses[$class] = $reflection;
            }
            $convertedValues = $reflection instanceof ConstructorPlan
                ? $reflection->decode($values, $path)
                : ConstructorDecoder::convert($reflection, $values, $path);
            if ($convertedValues instanceof DecodeError) {
                return $convertedValues;
            }
            $assignments = [];
            if ($reflection instanceof ReflectionClass) {
                $properties = PublicProperties::resolve($reflection);
                $assignments =
                    $properties === [] || $properties instanceof DecodeError
                        ? $properties
                        : PublicPropertyHydrator::prepare($class, $properties, $values, $path);
                if ($assignments instanceof DecodeError) {
                    return $assignments;
                }
                if ($properties === []) {
                    self::cacheConstructorPlan($class);
                }
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

    /** @param class-string $class */
    private static function cacheConstructorPlan(string $class): void
    {
        $plan = ConstructorDecoder::cachedPlan($class);
        if ($plan !== null) {
            self::$validatedClasses[$class] = $plan;
            self::$scalarPlans += array_filter([$class => $plan->scalarOnly]);
        }
    }
}
