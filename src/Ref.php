<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;

/**
 * Allows you to create reusable schemas for scalars.
 *
 * @example
 * ```
 * #[Format('uuid')]
 * class AccountId {}
 *
 * class Account {
 *     public function __construct(#[Ref(new AccountId())] public string $id) {}
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Ref
{
    public function __construct(public object $target)
    {
    }
}
