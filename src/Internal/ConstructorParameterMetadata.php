<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionProperty;

/** @internal */
final class ConstructorParameterMetadata
{
    public static function literalDocComment(string|false $docComment): string|false
    {
        return $docComment !== false && FieldTypeNameResolver::mayContainLiteralMarker($docComment)
            ? $docComment
            : false;
    }

    public static function isRecoverable(ReflectionProperty|null $property): bool
    {
        return (
            $property !== null
            && ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
            === ReflectionProperty::IS_PUBLIC
        );
    }
}
