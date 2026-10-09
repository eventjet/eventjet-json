<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, list<string>|null> */
    private static array $resolved = [];

    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): array|null
    {
        $key = $field instanceof ReflectionParameter
            ? ($field->getDeclaringClass()?->getName() ?? '')
            . '::'
            . $field->getDeclaringFunction()->getName()
            . '::$'
            . $field->getName()
            : $field->getDeclaringClass()->getName() . '::$' . $field->getName();
        if (array_key_exists($key, self::$resolved)) {
            return self::$resolved[$key];
        }
        return self::$resolved[$key] = PhpDocLiteralField::resolveUncached($field);
    }
}
