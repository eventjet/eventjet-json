<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * The property that collects the members that no declared field claims, with the JSON names that the declared fields
 * claim. Kept apart from TargetClass, so that classes without #[OtherMembers] do not load it.
 *
 * @internal
 */
final readonly class OtherMembersMapping
{
    /**
     * @param string $property The name of the property marked with #[OtherMembers].
     * @param array<array-key, string> $declared The names of the other public instance properties, keyed by JSON name.
     */
    public function __construct(
        public string $property,
        public array $declared,
    ) {}
}
