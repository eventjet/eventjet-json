<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

use function count;

/**
 * Selects the tag that PHPStan reads for a constructor parameter or a property.
 *
 * @internal
 */
final class PhpStanTagSelector
{
    /**
     * A later tag overrides an earlier one, and prefixed tags override `@param`.
     *
     * @param list<PhpStanTag> $tags
     */
    public static function parameter(array $tags, string $name): PhpStanType|null
    {
        $type = null;
        foreach (PhpStanReader::PARAM_TAGS as $kind) {
            foreach ($tags as $tag) {
                $type = $tag->name === $kind && $tag->variable === '$' . $name ? $tag->type : $type;
            }
        }
        return $type;
    }

    /**
     * Unnamed tags of the highest-priority kind take precedence over named tags, and only a single unnamed tag counts.
     *
     * @param list<PhpStanTag> $tags
     */
    public static function property(array $tags, string $name): PhpStanType|null
    {
        $unnamed = null;
        $named = null;
        foreach (PhpStanReader::VAR_TAGS as $kind) {
            $types = [];
            foreach ($tags as $tag) {
                if ($tag->name !== $kind) {
                    continue;
                }
                if ($tag->variable === '') {
                    $types[] = $tag->type;
                    continue;
                }
                $named = $tag->variable === '$' . $name ? $tag->type : $named;
            }
            $unnamed = $types === [] ? $unnamed : $types;
        }
        if ($unnamed === null) {
            return $named;
        }
        return count($unnamed) === 1 ? $unnamed[0] : null;
    }
}
