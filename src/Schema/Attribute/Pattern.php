<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema\Attribute;

use Attribute;

/**
 * Sets the "pattern" keyword in the generated JSON Schema.
 *
 * Can be applied to a property to annotate a single field, or to a class to annotate the schema of the entire class
 * (useful for named string types that implement JsonSerializable).
 *
 * ```php
 * // On a property:
 * public function __construct(
 *     #[Pattern('[1-9][0-9]*')]
 *     public string $userId,
 * ) {}
 *
 * // On a class:
 * #[Pattern('[1-9][0-9]*')]
 * final readonly class UserId implements JsonSerializable { ... }
 * ```
 *
 * @see https://json-schema.org/understanding-json-schema/reference/string#regexp
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Pattern
{
    public function __construct(
        public string $pattern,
    ) {
    }
}
