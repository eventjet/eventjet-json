<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use JsonSerializable;
use Override;

final class JsonSerializableWithObjectShape implements JsonSerializable
{
    public function __construct(
        private readonly StringStatus $status,
    ) {
    }

    /**
     * @return object{status: StringStatus}
     */
    #[Override]
    public function jsonSerialize(): object
    {
        return (object)['status' => $this->status];
    }
}
