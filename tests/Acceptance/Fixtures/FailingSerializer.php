<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use JsonSerializable;
use Override;
use RuntimeException;

/** @internal */
final class FailingSerializer implements JsonSerializable
{
    private static RuntimeException|null $error = null;

    public static function error(): RuntimeException
    {
        return self::$error ??= new RuntimeException('Serializer failed.');
    }

    /** @throws RuntimeException */
    #[Override]
    public function jsonSerialize(): never
    {
        throw self::error();
    }
}
