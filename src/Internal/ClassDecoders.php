<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Throwable;

/**
 * Caches the decoder of each class. Decoders that refer to classes that are not loaded yet, and declarations that fail,
 * are compiled again on the next use. Errors leave this class naming the class whose object could not be created.
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
            $decoder = self::$decoders[$class] ?? self::get($class);
            /** @var T|DecodeError */
            return $decoder instanceof DecodeError ? $decoder : $decoder->decode($object, $path);
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
    }
}
