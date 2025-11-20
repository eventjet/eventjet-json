<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\Ref;

final class TakesAccountId
{
    public function __construct(#[Ref(new AccountId())] public string $accountId)
    {
    }
}
