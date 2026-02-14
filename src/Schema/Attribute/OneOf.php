<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema\Attribute;

use Attribute;

use function array_values;

/**
 * Produces an "anyOf" keyword in the generated JSON Schema, listing each variant's schema as a reference.
 *
 * Apply to an interface or abstract class that acts as a discriminated union of concrete types.
 *
 * ```php
 * #[OneOf(VariantA::class, VariantB::class)]
 * interface MyUnion {}
 * ```
 *
 * @see https://json-schema.org/understanding-json-schema/reference/combining#anyOf
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class OneOf
{
    /** @var list<class-string> */
    public array $variants;

    /** @param class-string ...$variants */
    public function __construct(string ...$variants)
    {
        $this->variants = array_values($variants);
    }
}
