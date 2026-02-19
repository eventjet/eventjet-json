<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema\Attribute;

use Attribute;

/**
 * Sets the "minLength" keyword in the generated JSON Schema.
 *
 * Can be applied to a property to annotate a single field, or to a class to annotate the schema of the entire class
 * (useful for named string types that implement JsonSerializable).
 *
 * ```php
 * // On a property:
 * public function __construct(
 *     #[MinLength(1)]
 *     public string $name,
 * ) {}
 *
 * // On a class:
 * #[MinLength(1)]
 * final readonly class NonEmptyName implements JsonSerializable { ... }
 * ```
 *
 * @see https://json-schema.org/understanding-json-schema/reference/string#length
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class MinLength
{
    /**
     * @param int<0, max> $value
     */
    public function __construct(
        public int $value,
    ) {
    }
}
