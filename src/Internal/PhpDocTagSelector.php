<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function count;

/**
 * Selects the tag that PHPStan reads for a constructor parameter or a property.
 *
 * @internal
 */
final class PhpDocTagSelector
{
    /**
     * A later tag overrides an earlier one, and prefixed tags override `@param`.
     *
     * @param list<array{string, string, PhpDocType|null}> $tags
     */
    public static function parameter(array $tags, string $name): PhpDocType|null
    {
        $type = null;
        foreach (['@param', '@phan-param', '@psalm-param', '@phpstan-param'] as $kind) {
            foreach ($tags as [$tag, $variable, $value]) {
                $type = $tag === $kind && $variable === '$' . $name ? $value : $type;
            }
        }
        return $type;
    }

    /**
     * Unnamed tags of the highest-priority kind take precedence over named tags, and only a single unnamed tag counts.
     *
     * @param list<array{string, string, PhpDocType|null}> $tags
     */
    public static function property(array $tags, string $name): PhpDocType|null
    {
        $unnamed = null;
        $named = null;
        foreach (['@var', '@phan-var', '@psalm-var', '@phpstan-var'] as $kind) {
            $types = [];
            foreach ($tags as [$tag, $variable, $value]) {
                if ($tag !== $kind) {
                    continue;
                }
                if ($variable === '') {
                    $types[] = $value;
                    continue;
                }
                $named = $variable === '$' . $name ? $value : $named;
            }
            $unnamed = $types === [] ? $unnamed : $types;
        }
        if ($unnamed === null) {
            return $named;
        }
        return count($unnamed) === 1 ? $unnamed[0] : null;
    }
}
