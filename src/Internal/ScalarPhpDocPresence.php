<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

use function str_contains;

/** @internal */
final class ScalarPhpDocPresence
{
    /** @var array<string, string> */
    private static array $comments = [];

    public static function has(ReflectionParameter|ReflectionProperty $field): bool
    {
        if ($field instanceof ReflectionParameter) {
            $function = $field->getDeclaringFunction();
            $key = $function instanceof ReflectionMethod
                ? $function->getDeclaringClass()->getName() . '::' . $function->getName()
                : $function->getName() . '@' . $function->getFileName() . ':' . $function->getStartLine();
            $doc = self::comment($key, $function->getDocComment());

            return str_contains($doc, '$' . $field->getName());
        }

        $key = $field->getDeclaringClass()->getName() . '::$' . $field->getName();

        return str_contains(self::comment($key, $field->getDocComment()), '/**');
    }

    private static function comment(string $key, string|false $comment): string
    {
        $cached = self::$comments[$key] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $comment = (string) $comment;
        self::$comments[$key] = $comment;

        return $comment;
    }
}
