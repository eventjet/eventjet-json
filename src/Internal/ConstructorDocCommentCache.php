<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;

/** @internal */
final class ConstructorDocCommentCache
{
    /** @var array<class-string, string|false> */
    private static array $docComments = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    public static function for(ReflectionClass $class): string|false
    {
        $className = $class->getName();
        $docComment = self::$docComments[$className] ?? null;
        if ($docComment !== null) {
            return $docComment;
        }
        return self::$docComments[$className] = $class->getConstructor()?->getDocComment() ?? false;
    }
}
