<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function count;

/**
 * A `@param` or `@var` tag, or one of their `@phan-`, `@psalm-`, and `@phpstan-` variants, as the decoder reads it.
 *
 * @internal
 */
final readonly class PhpDocTag
{
    /**
     * @param string $name The tag name, such as `@phpstan-param`.
     * @param string $variable The variable name with its `$`, or an empty string for a `@var` tag without one.
     * @param PhpDocType|null $type The declared type, or null if the decoder does not support its syntax.
     */
    public function __construct(
        public string $name,
        public string $variable,
        public PhpDocType|null $type,
    ) {}

    /**
     * Returns the tag that PHPStan reads for a constructor parameter, or null if none does. A later tag overrides an
     * earlier one, and prefixed tags override `@param`.
     *
     * @param list<self> $tags
     */
    public static function forParameter(array $tags, string $name): self|null
    {
        $selected = null;
        foreach (['@param', '@phan-param', '@psalm-param', '@phpstan-param'] as $kind) {
            foreach ($tags as $tag) {
                $selected = $tag->name === $kind && $tag->variable === '$' . $name ? $tag : $selected;
            }
        }
        return $selected;
    }

    /**
     * Returns the tag that PHPStan reads for a property, or null if none does. Unnamed tags of the highest-priority kind
     * take precedence over named tags, and only a single unnamed tag counts.
     *
     * @param list<self> $tags
     */
    public static function forProperty(array $tags, string $name): self|null
    {
        $unnamed = null;
        $named = null;
        foreach (['@var', '@phan-var', '@psalm-var', '@phpstan-var'] as $kind) {
            $candidates = [];
            foreach ($tags as $tag) {
                if ($tag->name !== $kind) {
                    continue;
                }
                if ($tag->variable === '') {
                    $candidates[] = $tag;
                    continue;
                }
                $named = $tag->variable === '$' . $name ? $tag : $named;
            }
            $unnamed = $candidates === [] ? $unnamed : $candidates;
        }
        if ($unnamed === null) {
            return $named;
        }
        return count($unnamed) === 1 ? $unnamed[0] : null;
    }
}
