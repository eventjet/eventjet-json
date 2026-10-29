<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidInput;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use ReflectionClass;
use stdClass;
use Throwable;

use function enum_exists;

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
     */
    public static function get(string $class): ClassDecoder
    {
        return self::$decoders[$class] ?? self::compile($class);
    }

    /**
     * Compiles the decoder of a class, and caches it if it is complete. An exception other than a DecodeError is
     * reported as an unreadable declaration of the class.
     *
     * @param class-string $class
     * @throws DecodeError
     */
    private static function compile(string $class): ClassDecoder
    {
        $name = $class;
        try {
            $reflection = new ReflectionClass($class);
            $name = $reflection->getName();
            $decoder = ClassDecoder::compile($reflection);
        } catch (DecodeError $error) {
            throw $error->whileCreating($name);
        } catch (Throwable $error) {
            throw UnsupportedTarget::unreadable($error)->whileCreating($class);
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
        $decoder = self::$decoders[$class] ?? self::compile($class);
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
     * Decodes the root value of a document into a class or an enum. Unlike hydrate(), it returns the error, because
     * throwing it once more slows down every rejected document measurably.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function decodeRoot(string $class, mixed $value): object
    {
        $decoder = self::$decoders[$class] ?? null;
        // A class with a cached decoder is no enum, so warm decodes skip enum_exists(). Classes skip EnumRoots, so that
        // a request that decodes no enum root does not load it.
        $enum = $class;
        try {
            if ($decoder === null && enum_exists($enum)) {
                return EnumRoots::decode($class, $value);
            }
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
        if (!$value instanceof stdClass) {
            return InvalidInput::rootValue($value);
        }
        try {
            $decoder ??= self::compile($class);
        } catch (DecodeError $error) {
            return $error;
        }
        try {
            /** @var T */
            return $decoder->decode($value, '');
        } catch (DecodeError $error) {
            return $error->whileCreating($decoder->class);
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
    }
}
