<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use stdClass;
use Throwable;

use function get_object_vars;

/** @internal */
final class ObjectHydrator
{
    /** @var array<class-string, ReflectionClass<object>|ConstructorPlan> */
    private static array $validatedClasses = [];

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
            if ($reflection instanceof ConstructorPlan && $reflection->hydrate !== null) {
                $result = ($reflection->hydrate)($object);
                if ($result !== null) {
                    /** @var T $result */
                    return $result;
                }
            }
            /** @var array<string, array<array-key, mixed>|bool|float|int|object|string|null> $values */
            $values = get_object_vars($object);
            $uncached = $reflection === null;
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
                $assignments = PublicPropertyHydrator::prepare($reflection, $values, $path);
                if ($assignments instanceof DecodeError) {
                    return $assignments;
                }
                if ($uncached) {
                    self::rememberScalarPlan($class, $reflection);
                }
            }
            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            $object = new $class(...$convertedValues);
            PublicPropertyHydrator::assign($object, $assignments);

            return $object;
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @param class-string $class
     * @param ReflectionClass<object> $reflection
     * @throws \ReflectionException
     */
    private static function rememberScalarPlan(string $class, ReflectionClass $reflection): void
    {
        $properties = PublicProperties::resolve($reflection);
        $plan = ConstructorDecoder::scalarPlan($class);
        if ($properties === [] && $plan !== null) {
            self::$validatedClasses[$class] = $plan;
        }
    }
}
