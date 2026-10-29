<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use ReflectionException;

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
     * Decodes the root value of a document into a case of an enum. Other exceptions than a DecodeError are left to
     * ClassDecoders::decodeRoot(), which reports them as unreadable declarations of the enum.
     *
     * @template T of object
     * @param class-string<T> $enum
     * @return T|DecodeError
     * @throws ReflectionException
     */
    public static function decode(string $enum, mixed $value): object
    {
        try {
            /** @var T|DecodeError */
            return self::node($enum)->decodeRoot($value);
        } catch (DecodeError $error) {
            return $error;
        }
    }

    /**
     * Returns the node of an enum.
     *
     * @throws DecodeError If the enum cannot be decoded.
     * @throws ReflectionException
     */
    public static function node(string $enum): EnumNode
    {
        $node = self::$nodes[$enum] ?? null;
        if ($node !== null) {
            return $node;
        }
        try {
            TargetClass::validateEnum($enum);
        } catch (DecodeError $error) {
            throw $error->whileCreating($enum);
        }
        return self::$nodes[$enum] = EnumNode::of($enum);
    }
}
