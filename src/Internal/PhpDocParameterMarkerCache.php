<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

use function array_key_exists;

/** @internal */
final class PhpDocParameterMarkerCache
{
    /** @var array<string, array<string, bool>> */
    private static array $parameterMarkers = [];
    /** @var array<string, string> */
    private static array $functionDocComments = [];
    /** @var array<class-string, string|false> */
    private static array $constructorDocComments = [];

    /** @param class-string $class */
    public static function constructorDocComment(string $class, ReflectionMethod|null $constructor): string|false
    {
        return self::$constructorDocComments[$class] ??= $constructor?->getDocComment() ?? false;
    }

    public static function hasMarker(ReflectionParameter $field, string|false|null $docComment = null): bool
    {
        $markers = self::parameterMarkers($field->getDeclaringFunction(), $docComment);
        return $markers[$field->getName()] ?? false;
    }

    /** @return array<string, bool> */
    public static function parameterMarkers(
        ReflectionFunctionAbstract|null $function,
        string|false|null $docComment = null,
    ): array {
        if ($function === null || $docComment === false) {
            return [];
        }
        $functionName = $function->getName();
        $functionKey = $functionName;
        if ($function instanceof ReflectionMethod) {
            $functionKey = $function->getDeclaringClass()->getName() . '::' . $functionName;
        }
        if ($functionName === '{closure}') {
            $functionKey .= ':' . (string) $function->getFileName() . ':' . (string) $function->getStartLine();
        }
        if (
            array_key_exists($functionKey, self::$parameterMarkers)
            && ($docComment === null || $docComment === (self::$functionDocComments[$functionKey] ?? null))
        ) {
            return self::$parameterMarkers[$functionKey];
        }
        $doc = $docComment ?? $function->getDocComment();
        if ($doc === false || $doc === '') {
            if ($docComment === null) {
                self::$parameterMarkers[$functionKey] = [];
                self::$functionDocComments[$functionKey] = '';
            }
            return [];
        }
        self::$parameterMarkers[$functionKey] = PhpDocLiteralFieldMarker::parameterMarkers($function, $doc);
        self::$functionDocComments[$functionKey] = $doc;
        return self::$parameterMarkers[$functionKey];
    }
}
