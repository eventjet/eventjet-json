<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\ConstructorFailure;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Throwable;

use function get_object_vars;

/**
 * Caches the decoder of each class and hydrates objects with it. Decoders that refer to classes that are not loaded
 * yet, and declarations that fail, are compiled again on the next use. Errors leave this class naming the class whose
 * object could not be created.
 *
 * @internal
 */
final class ClassDecoders
{
    /** @var array<class-string, ClassDecoder> */
    private static array $decoders = [];

    /**
     * @param class-string $class
     * @phpstan-impure
     * @throws ReflectionException
     */
    public static function get(string $class): ClassDecoder|DecodeError
    {
        $decoder = self::$decoders[$class] ?? null;
        if ($decoder !== null) {
            return $decoder;
        }
        $reflection = new ReflectionClass($class);
        $decoder = ClassDecoder::compile($reflection);
        if ($decoder instanceof DecodeError) {
            return $decoder->whileCreating($reflection->getName());
        }
        if ($decoder->complete) {
            self::$decoders[$class] = $decoder;
        }
        return $decoder;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function hydrate(string $class, stdClass $object, string $path = ''): object
    {
        try {
            // Skips the call to get() for cached decoders, because a call per object slows down hydration measurably.
            $decoder = self::$decoders[$class] ?? null;
            $decoder = $decoder === null ? self::get($class) : $decoder;
            if ($decoder instanceof DecodeError) {
                return $decoder;
            }
            // Hydrates inline instead of calling the decoder, because a call per object slows down hydration measurably.
            /** @var array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values */
            $values = get_object_vars($object);
            $arguments = $decoder->constructor->decode($values, $path);
            if ($arguments instanceof DecodeError) {
                return $arguments->whileCreating($decoder->class);
            }
            $assignments = $decoder->properties === []
                ? []
                : PublicProperties::decode($decoder->properties, $values, $path);
            if ($assignments instanceof DecodeError) {
                return $assignments->whileCreating($decoder->class);
            }
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
        try {
            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             * @var T $hydrated
             */
            $hydrated = new $decoder->class(...$arguments);
        } catch (Throwable $error) {
            $missing = $decoder->constructor->missesArgument($arguments);
            $failure = $missing ? InvalidValue::missingArgument($error) : ConstructorFailure::constructorThrew($error);
            return $failure->whileCreating($class);
        }
        foreach ($assignments as $assignment) {
            try {
                $assignment[0]->setValue($hydrated, $assignment[1]);
            } catch (Throwable $error) {
                return PublicProperties::failure($assignment[0], $error)->whileCreating($class);
            }
        }
        return $hydrated;
    }
}
