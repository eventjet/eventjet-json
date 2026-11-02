<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final class OptionalJsonValue implements JsonSerializable
{
    use MappedJsonFields;

    public JsonValue|null $extra = null;

    public function __construct(
        public readonly JsonValue|null $value = null,
    ) {}
}
