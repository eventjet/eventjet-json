<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;

/** @internal */
final class ConstructorParameterLiteralMetadata
{
    public static function forParameter(ReflectionParameter $parameter, string|false $docComment): string|false
    {
        if ($docComment === false) {
            return false;
        }
        $hasMarker = PhpDocParameterMarkerCache::hasMarker($parameter, $docComment);
        return $hasMarker ? $docComment : false;
    }
}
