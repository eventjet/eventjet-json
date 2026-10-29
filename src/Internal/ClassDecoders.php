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
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function get(string $class): ClassDecoder
    {
        $decoder = self::$decoders[$class] ?? null;
        if ($decoder !== null) {
            return $decoder;
        }
        $reflection = new ReflectionClass($class);
        try {
            $decoder = ClassDecoder::compile($reflection);
        } catch (DecodeError $error) {
            throw $error->whileCreating($reflection->getName());
        }
        if ($decoder->complete) {
            self::$decoders[$class] = $decoder;
        }
        return $decoder;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @throws DecodeError
     */
    public static function hydrate(string $class, stdClass $object, string $path = ''): object
    {
        // Skips the call to get() for cached decoders, because a call per object slows down hydration measurably.
        $decoder = self::$decoders[$class] ?? self::compiled($class);
        try {
            /** @var T */
            return $decoder->decode($object, $path);
        } catch (DecodeError $error) {
            throw $error->whileCreating($decoder->class);
        } catch (Throwable $error) {
            throw UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
    }

    /**
     * Decodes the root object of a document. Unlike hydrate(), it returns the error, because throwing it once more
     * slows down every rejected document measurably.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function decodeRoot(string $class, stdClass $object): object
    {
        try {
            $decoder = self::$decoders[$class] ?? self::compiled($class);
        } catch (DecodeError $error) {
            return $error;
        }
        try {
            /** @var T */
            return $decoder->decode($object, '');
        } catch (DecodeError $error) {
            return $error->whileCreating($decoder->class);
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
    }

    /**
     * @param class-string $class
     * @throws DecodeError
     */
    private static function compiled(string $class): ClassDecoder
    {
        try {
            return self::get($class);
        } catch (DecodeError $error) {
            throw $error;
        } catch (Throwable $error) {
            throw UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
    }
}
