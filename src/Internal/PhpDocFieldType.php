<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function preg_match;

/** @internal */
final class PhpDocFieldType
{
    /** @var array<string, MetadataCache<list<array{PhpDocType, string}>>> */
    private static array $declarations = [];
    /** @var array<string, MetadataCache<array<string, PhpDocType>>> */
    private static array $parameters = [];

    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        $tag = $field instanceof ReflectionParameter ? '@param' : '@var';
        /** @var MetadataCache<list<array{PhpDocType, string}>> $cache */
        $cache = self::$declarations[$tag] ?? new MetadataCache();
        self::$declarations[$tag] = $cache;
        $doc = $doc === false ? '' : $doc;
        $types = $cache->resolve(
            $doc,
            /** @return list<array{PhpDocType, string}> */ static fn(): array => PhpDocFieldTypeParser::parse(
                $doc,
                $tag,
            ),
        );
        if ($field instanceof ReflectionParameter) {
            /** @var MetadataCache<array<string, PhpDocType>> $parameters */
            $parameters = self::$parameters[$tag] ?? new MetadataCache();
            self::$parameters[$tag] = $parameters;
            $byName = $parameters->resolve(
                $doc,
                /** @return array<string, PhpDocType> */ static fn(): array => self::parameters($types),
            );
            return $byName[$field->getName()] ?? null;
        }
        foreach ($types as $parsed) {
            [$type, $remainder] = $parsed;
            $suffixPattern = '/\\A(?:\\s+[^\\s|&<>\\[\\],?:{}].*)?\\z/s';
            $validSuffix = preg_match($suffixPattern, $remainder) === 1;

            if ($validSuffix) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param list<array{PhpDocType, string}> $types
     * @return array<string, PhpDocType>
     */
    private static function parameters(array $types): array
    {
        $parameters = [];
        foreach ($types as [$type, $remainder]) {
            $matches = [];
            preg_match('/\\A\\s+\\$([^\\s]+)(?:\\s|$)/', $remainder, $matches);
            $parameterName = $matches[1] ?? null;
            if ($parameterName !== null) {
                $parameters[$parameterName] = $type;
            }
        }
        return $parameters;
    }
}
