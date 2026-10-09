<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function preg_match;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, list<string>|null> */
    private static array $resolved = [];
    /** @var array<string, bool> */
    private static array $literalMarkers = [];

    public static function mayContainLiteral(ReflectionParameter|ReflectionProperty $field): bool
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        if ($doc === false) {
            return false;
        }
        $key = $field instanceof ReflectionParameter
            ? ($field->getDeclaringClass()?->getName() ?? '')
            . '::'
            . $field->getDeclaringFunction()->getName()
            . '@'
            . (string) $field->getDeclaringFunction()->getStartLine()
            : $field->getDeclaringClass()->getName() . '::$' . $field->getName();
        return self::$literalMarkers[$key] ??= preg_match('~[\'"\d:]|\b(?:true|false|null)\b~', subject: $doc) === 1;
    }

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
