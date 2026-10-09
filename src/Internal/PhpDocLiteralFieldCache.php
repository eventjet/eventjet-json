<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use WeakMap;

use function enum_exists;
use function preg_match;

/** @internal */
final class PhpDocLiteralFieldCache
{
    /** @var array<string, bool> */
    private static array $literalMarkers = [];
    /** @var WeakMap<object, array{bool, list<string>|null}>|null */
    private static WeakMap|null $literalPresence = null;

    /** @throws ReflectionException */
    public static function hasLiteral(ReflectionParameter|ReflectionProperty $field): bool
    {
        if (self::$literalPresence === null) {
            self::$literalPresence = new WeakMap();
        }
        $cache = self::$literalPresence;
        if ($cache->offsetExists($field)) {
            /** @var array{bool, list<string>|null} $cached */
            $cached = $cache[$field];
            return $cached[0];
        }
        $hasLiteral = self::mayContainLiteral($field) && PhpDocFieldType::resolve($field)?->containsLiteral() === true;
        $resolved = $hasLiteral ? PhpDocLiteralField::resolveUncached($field) : null;
        $cache[$field] = [$hasLiteral, $resolved];
        return $hasLiteral;
    }

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
        $matches = [];
        $found = $field instanceof ReflectionParameter
            ? preg_match(
                '~@param\s+(.+?)\s+(?:&|\.\.\.)?\$' . preg_quote($field->getName(), delimiter: '~') . '(?:\s|$)~s',
                $doc,
                $matches,
            )
            : preg_match('~@var\s+(.+?)(?=\s+\$[A-Za-z_]\w*\s*(?:\r?\n|\*/|$)|\s*(?:\r?\n|\*/|$))~s', $doc, $matches);
        if ($found !== 1) {
            return false;
        }
        $type = trim($matches[1] ?? '');
        return self::$literalMarkers[$type] ??=
            preg_match('~[\'"\d:]|\b(?:true|false|null|[A-Z][A-Za-z0-9_]*)\b~', subject: $type) === 1;
    }

    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): array|null
    {
        self::hasLiteral($field);
        assert(self::$literalPresence !== null, description: 'Literal field metadata has been initialized.');
        $cached = self::$literalPresence[$field];
        return $cached[1];
    }
}
