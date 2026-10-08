<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Throwable;

use function get_object_vars;

/** @internal */
final class ObjectHydrator
{
    /** @var array<class-string, ReflectionClass<object>> */
    private static array $validatedClasses = [];
    /** @var array<class-string, ConstructorPlan|false> */
    private static array $scalarPlans = [];

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function hydrate(string $class, stdClass $object, string $path = ''): object
    {
        try {
            $plan = self::$scalarPlans[$class] ?? null;
            return $plan instanceof ConstructorPlan
                ? self::hydrateScalars($class, $object, $path, $plan)
                : self::hydrateWithMetadata($class, $object, $path);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     * @throws ReflectionException
     * @throws JsonException
     */
    private static function hydrateScalars(string $class, stdClass $object, string $path, ConstructorPlan $plan): object
    {
        /** @var array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values */
        $values = get_object_vars($object);
        $arguments = $plan->decode($values, $path);
        return $arguments instanceof DecodeError ? $arguments : self::instantiate($class, $arguments);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     * @throws ReflectionException
     * @throws JsonException
     */
    private static function hydrateWithMetadata(string $class, stdClass $object, string $path): object
    {
        /** @var ReflectionClass<T>|null $reflection */
        $reflection = self::$validatedClasses[$class] ?? null;
        if ($reflection === null) {
            $reflection = new ReflectionClass($class);
            $targetError = RootTypeValidator::validate($reflection);
            if ($targetError !== null) {
                return $targetError;
            }
            self::$validatedClasses[$class] = $reflection;
        }
        /** @var array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values */
        $values = get_object_vars($object);
        $convertedValues = ConstructorDecoder::convert($reflection, $values, $path);

        if ($convertedValues instanceof DecodeError) {
            return $convertedValues;
        }

        $assignments = PublicPropertyHydrator::prepare($reflection, $values, $path);
        if ($assignments instanceof DecodeError) {
            return $assignments;
        }

        $object = self::instantiate($class, $convertedValues);
        PublicPropertyHydrator::assign($object, $assignments);
        if ((self::$scalarPlans[$class] ?? null) === null) {
            $properties = PublicProperties::resolve($reflection);
            self::$scalarPlans[$class] = $properties === [] ? ConstructorDecoder::scalarPlan($class) ?? false : false;
        }

        return $object;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $values
     * @return T
     */
    private static function instantiate(string $class, array $values): object
    {
        /**
         * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
         * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
         */
        return new $class(...$values);
    }
}
