<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use ReflectionClass;
use ReflectionException;

use function class_exists;
use function interface_exists;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function preg_quote;
use function str_replace;
use function var_export;

/** @internal */
final class PhpDocConstantValues
{
    /**
     * Returns the literal of a constant value, named like PHPDoc would spell it, or `null`. Returns null for other
     * values.
     *
     * @return PhpDocLiteral|'null'|null
     */
    public static function literal(mixed $value): PhpDocLiteral|string|null
    {
        if ($value instanceof BackedEnum) {
            return new PhpDocLiteral($value, $value::class . '::' . $value->name);
        }
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            return PhpDocLiteral::string($value);
        }
        return is_int($value) ? new PhpDocLiteral($value, (string) $value) : self::boolOrFloat($value);
    }

    private static function boolOrFloat(mixed $value): PhpDocLiteral|null
    {
        $valid = is_bool($value) || is_float($value) && is_finite($value);
        return $valid ? new PhpDocLiteral($value, var_export($value, return: true)) : null;
    }

    /**
     * @return non-empty-list<PhpDocLiteral|'null'>|null
     * @throws ReflectionException
     */
    public static function resolve(string $class, string $member): array|null
    {
        if (!class_exists($class) && !interface_exists($class)) {
            return null;
        }
        $pattern = '/\A' . str_replace('\\*', replace: '.*', subject: preg_quote($member, delimiter: '/')) . '\z/';
        $names = [];
        foreach (new ReflectionClass($class)->getReflectionConstants() as $candidate) {
            $matches = preg_match($pattern, $candidate->getName());
            if ($matches !== 1) {
                continue;
            }
            $value = self::literal($candidate->getValue());
            if ($value === null) {
                return null;
            }
            $names[] = $value;
        }
        return $names === [] ? null : $names;
    }
}
