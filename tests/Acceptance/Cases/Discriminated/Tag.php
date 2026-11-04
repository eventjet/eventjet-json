<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use BackedEnum;

/**
 * The discriminator of a union in a fixture set, as errors report it.
 *
 * @internal
 */
final readonly class Tag
{
    /**
     * @param string $member The JSON name of the discriminator.
     * @param string $allowed The declared discriminator values, as errors list them.
     * @param string|int|BackedEnum $undeclared A discriminator value that no class of the union declares.
     * @param string $property The PHP name of the discriminator.
     */
    public function __construct(
        public string $member,
        public string $allowed,
        public string|int|BackedEnum $undeclared,
        public string $property,
    ) {}

    public static function of(string $member, string $allowed, string|int|BackedEnum $undeclared): self
    {
        return new self($member, $allowed, $undeclared, $member);
    }
}
