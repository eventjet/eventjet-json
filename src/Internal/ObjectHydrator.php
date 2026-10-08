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
    /** @var array<class-string, ReflectionClass<object>> */
    private static array $validatedClasses = [];

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function hydrate(string $class, stdClass $object, string $path = ''): object
    {
        try {
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
            $collections = ObjectTypeValidator::validate($reflection, $values, $path);

            if ($collections instanceof DecodeError) {
                return $collections;
            }

            $convertedValues = ObjectValueConverter::convert($reflection, $values, $collections, $path);

            if ($convertedValues instanceof DecodeError) {
                return $convertedValues;
            }

            $assignments = PublicPropertyHydrator::prepare($reflection, $values, $path);
            if ($assignments instanceof DecodeError) {
                return $assignments;
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
}
