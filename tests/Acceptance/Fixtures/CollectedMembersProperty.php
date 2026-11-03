<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\JsonValue;
use Eventjet\Json\MappedJsonFields;
use Eventjet\Json\OtherMembers;
use JsonSerializable;

/** @internal */
final class CollectedMembersProperty implements JsonSerializable
{
    use MappedJsonFields;

    /** @var array<int|string, JsonValue> */
    #[OtherMembers]
    public array $others = [];

    public string $name = '';

    public function __construct()
    {
        unset($this->others, $this->name);
    }
}
