<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema\Attribute;

use Attribute;

/**
 * Sets the "uniqueItems" keyword to `true` in the generated JSON Schema.
 *
 * Can be applied to a property to annotate a single field, or to a class to annotate the schema of the entire class
 * (useful for set types that implement JsonSerializable).
 *
 * ```php
 * // On a property:
 * public function __construct(
 *     #[UniqueItems]
 *     /** @var list<string> {@*}
 *     public array $tags,
 * ) {}
 *
 * // On a class:
 * #[UniqueItems]
 * final readonly class TagSet implements JsonSerializable { ... }
 * ```
 *
 * @see https://json-schema.org/understanding-json-schema/reference/array#uniqueItems
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class UniqueItems
{
}
