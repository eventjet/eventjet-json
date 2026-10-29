<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidInput;
use Eventjet\Json\Internal\Node\EnumNode;
use ReflectionException;

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
     * Decodes a root value that is not a JSON object, which only an enum accepts.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $class, mixed $value): object
    {
        try {
            $node = self::node($class);
        } catch (DecodeError $error) {
            return $error;
        }
        if ($node === null) {
            return InvalidInput::rootValue($value);
        }
        /** @var T|DecodeError */
        return $node->decodeRoot($value);
    }

    /**
     * Returns the node of an enum, or null for a class.
     *
     * @throws DecodeError If the enum cannot be decoded.
     */
    public static function node(string $class): EnumNode|null
    {
        $node = self::$nodes[$class] ?? null;
        if ($node !== null || !enum_exists($class)) {
            return $node;
        }
        return ClassDecoders::readable(
            $class,
            /** @throws DecodeError|ReflectionException */ static function () use ($class): EnumNode {
                try {
                    TargetPosition::Root->validateEnum($class);
                } catch (DecodeError $error) {
                    throw $error->whileCreating($class);
                }
                return self::$nodes[$class] = EnumNode::of($class);
            },
        );
    }
}
