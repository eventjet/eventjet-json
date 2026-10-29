<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\Internal\Node\EnumNode;
use ReflectionException;
use Throwable;

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
     * Decodes the root value of a document into a case of an enum.
     *
     * @template T of object
     * @param class-string<T> $enum
     * @return T|DecodeError
     */
    public static function decode(string $enum, mixed $value): object
    {
        try {
            /** @var T|DecodeError */
            return self::node($enum)->decodeRoot($value);
        } catch (DecodeError $error) {
            return $error;
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($enum);
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
