<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\JsonValue;
use JsonSerializable;
use Override;

use function array_filter;

/** @internal */
final class OptionalJsonValue implements JsonSerializable
{
    public JsonValue|null $extra = null;

    public function __construct(
        public readonly JsonValue|null $value = null,
    ) {}

    /** Leaves out absent members, which json_encode() would write as JSON null. */
    #[Override]
    public function jsonSerialize(): object
    {
        return (object) array_filter(
            ['value' => $this->value, 'extra' => $this->extra],
            static fn(JsonValue|null $member): bool => $member !== null,
        );
    }
}
