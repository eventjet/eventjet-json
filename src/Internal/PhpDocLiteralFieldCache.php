<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function enum_exists;
use function preg_match;
use function serialize;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, list<string>|null> */
    private static array $resolved = [];
    /** @var array<string, bool> */
    private static array $literalMarkers = [];

    public static function mayContainLiteral(ReflectionParameter|ReflectionProperty $field): bool
    {
        $native = $field->getType();
        if ($native instanceof ReflectionNamedType && !$native->isBuiltin() && !enum_exists($native->getName())) {
            return false;
        }
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        if ($doc === false) {
            return false;
        }
        return self::$literalMarkers[$doc] ??=
            preg_match('~[\'"\d:]|\b(?:true|false|null|[A-Z][A-Za-z0-9_]*)\b~', subject: $doc) === 1;
    }

    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): array|null
    {
        $key = $field instanceof ReflectionParameter
            ? serialize([
                'parameter',
                $field->getDeclaringClass()?->getName(),
                $field->getDeclaringFunction()->getName(),
                $field->getName(),
            ])
            : serialize(['property', $field->getDeclaringClass()->getName(), $field->getName()]);
        if (array_key_exists($key, self::$resolved)) {
            return self::$resolved[$key];
        }
        return self::$resolved[$key] ??= PhpDocLiteralField::resolveUncached($field);
    }
}
