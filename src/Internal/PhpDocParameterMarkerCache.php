<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
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

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    public static function constructorDocComment(ReflectionClass $class): string|false
    {
        return self::$constructorDocComments[$class->getName()] ??= $class->getConstructor()?->getDocComment() ?? false;
    }

    public static function hasMarker(ReflectionParameter $field, string|false|null $docComment = null): bool
    {
        $function = $field->getDeclaringFunction();
        $functionName = $function->getName();
        $functionKey = ($field->getDeclaringClass()?->getName() ?? '') . '::' . $functionName;
        if ($functionName === '{closure}') {
            $functionKey .= ':' . (string) $function->getFileName() . ':' . (string) $function->getStartLine();
        }
        if (
            $docComment !== false
            && array_key_exists($functionKey, self::$parameterMarkers)
            && ($docComment === null || $docComment === (self::$functionDocComments[$functionKey] ?? null))
        ) {
            return self::$parameterMarkers[$functionKey][$field->getName()] ?? false;
        }
        $doc = $docComment ?? $function->getDocComment();
        if ($doc === false || $doc === '') {
            if ($docComment === null) {
                self::$parameterMarkers[$functionKey] = [];
                self::$functionDocComments[$functionKey] = '';
            }
            return false;
        }
        self::$parameterMarkers[$functionKey] = PhpDocLiteralFieldMarker::parameterMarkers($function, $doc);
        self::$functionDocComments[$functionKey] = $doc;
        return self::$parameterMarkers[$functionKey][$field->getName()] ?? false;
    }
}
