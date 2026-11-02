<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * The member that #[Discriminator] names for a field, and whether a union of several classes in the field's type uses
 * it.
 *
 * @internal
 */
final class FieldDiscriminator
{
    public private(set) bool $used = false;

    public function __construct(
        public readonly string $name,
    ) {}

    public function use(): void
    {
        $this->used = true;
    }
}
