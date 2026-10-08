<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use ReflectionClass;
use ReflectionException;

use function class_exists;
use function interface_exists;
use function is_finite;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_quote;
use function str_replace;
use function var_export;

/** @internal */
final class PhpDocConstantValues
{
    public static function name(mixed $value): string|null
    {
        if ($value instanceof BackedEnum) {
            return $value::class . '::' . $value->name;
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return "'" . str_replace(['\\', "'"], replace: ['\\\\', "\\'"], subject: $value) . "'";
        }
        if (is_float($value) && !is_finite($value)) {
            return null;
        }
        return is_scalar($value) ? var_export($value, return: true) : null;
    }

    /**
     * @return list<string>|null
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
            $value = self::name($candidate->getValue());
            if ($value === null) {
                return null;
            }
            $names[] = $value;
        }
        return $names === [] ? null : $names;
    }
}
