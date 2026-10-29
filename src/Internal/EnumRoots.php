<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidInput;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\Internal\Node\EnumNode;
use ReflectionException;
use Throwable;

use function enum_exists;

/**
 * Decodes the root value of a document into a backed enum, and caches the node of each enum.
 *
 * @internal
 */
final class EnumRoots
{
    /** @var array<string, EnumNode> */
    private static array $nodes = [];

    /**
     * Decodes the root value of a document into a case of an enum. For a class, it returns the error for a value that
     * is not a JSON object.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $class, mixed $value): object
    {
        try {
            $node = self::node($class);
            if ($node === null) {
                return InvalidInput::rootValue($value);
            }
            /** @var T|DecodeError */
            return $node->decodeRoot($value);
        } catch (DecodeError $error) {
            return $error;
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($class);
        }
    }

    /**
     * Returns the node of an enum, or null for a class.
     *
     * @throws DecodeError If the enum cannot be decoded.
     * @throws ReflectionException
     */
    public static function node(string $class): EnumNode|null
    {
        $node = self::$nodes[$class] ?? null;
        if ($node !== null || !enum_exists($class)) {
            return $node;
        }
        try {
            TargetClass::validateEnum($class);
        } catch (DecodeError $error) {
            throw $error->whileCreating($class);
        }
        return self::$nodes[$class] = EnumNode::of($class);
    }
}
