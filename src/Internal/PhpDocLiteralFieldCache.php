<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, list<string>|null> */
    private static array $resolved = [];

    /**
     * @param Closure(): list<string>|null $resolve
     * @return list<string>|null
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field, Closure $resolve): array|null
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
        return self::$resolved[$key] = $resolve();
    }
}
