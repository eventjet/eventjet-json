<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\AmbiguousUnion;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;

use function get_debug_type;
use function is_float;
use function is_int;
use function json_encode;

/** @internal */
final class PhpDocLiteralUnionValidator
{
    /**
     * @param list<Node> $members
     * @throws ReflectionException
     */
    public static function validate(string $path, array $members): DecodeError|null
    {
        foreach ($members as $member) {
            if (!$member instanceof LiteralNode) {
                continue;
            }
            $literal = $member->value();
            foreach ($members as $other) {
                $overlaps = self::overlaps($literal, $other);
                if ($overlaps) {
                    return AmbiguousUnion::overlappingLiterals($path);
                }
            }
        }
        return null;
    }

    /** @throws ReflectionException */
    private static function overlaps(string|int|float|bool|BackedEnum $literal, Node $other): bool
    {
        $value = $literal instanceof BackedEnum ? $literal->value : $literal;
        if ($other instanceof LiteralNode) {
            $candidate = $other->value();
            $raw = $candidate instanceof BackedEnum ? $candidate->value : $candidate;
            return $candidate !== $literal && json_encode($raw) === json_encode($value);
        }
        $name = (string) $other;
        $enumOverlap = PhpDocLiteralEnumOverlap::matches($literal, $name);
        return (
            $enumOverlap
            || $literal instanceof BackedEnum
            && $name === get_debug_type($value)
            || is_float($literal)
            && $name === 'int'
            && $literal === (float) (int) $literal
            || is_int($literal)
            && $name === 'float'
        );
    }
}
