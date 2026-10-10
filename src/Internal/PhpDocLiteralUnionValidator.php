<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use ReflectionException;

use function array_filter;
use function get_debug_type;
use function is_float;
use function is_int;
use function json_encode;

/** @internal */
final class PhpDocLiteralUnionValidator
{
    /**
     * @param class-string $class
     * @param list<string> $names
     * @throws ReflectionException
     */
    public static function validate(string $class, string $path, array $names): DecodeError|null
    {
        foreach (array_filter($names, PhpDocType::literalSyntax(...)) as $name) {
            $literal = PhpDocLiteral::value($name);
            if ($literal === null) {
                continue;
            }
            foreach ($names as $other) {
                $overlaps = self::overlaps($literal, $other);
                if ($overlaps) {
                    return DecodeError::nonInstantiableTarget(
                        $class,
                        'Field '
                        . $path
                        . ' has overlapping literal union members. JSON cannot identify which PHP type to restore.',
                    );
                }
            }
        }
        return null;
    }

    /** @throws ReflectionException */
    private static function overlaps(string|int|float|bool|BackedEnum $literal, string $other): bool
    {
        $value = $literal instanceof BackedEnum ? $literal->value : $literal;
        $candidate = PhpDocLiteral::value($other);
        if ($candidate !== null) {
            $raw = $candidate instanceof BackedEnum ? $candidate->value : $candidate;
            return $candidate !== $literal && json_encode($raw) === json_encode($value);
        }
        $enumOverlap = PhpDocLiteralEnumOverlap::matches($literal, $other);
        return (
            $enumOverlap
            || $literal instanceof BackedEnum
            && $other === get_debug_type($value)
            || is_float($literal)
            && $other === 'int'
            && $literal === (float) (int) $literal
            || is_int($literal)
            && $other === 'float'
        );
    }
}
