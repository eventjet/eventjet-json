<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use function opcache_get_status;

/** @internal */
final class FpmRuntime
{
    /** @return array{enabled: bool, misses: int, jit: bool} */
    public static function status(): array
    {
        /** @var array{opcache_enabled: bool, opcache_statistics: array{misses: int}, jit: array{enabled: bool}}|false $status */
        $status = opcache_get_status(false);
        if ($status === false) {
            return ['enabled' => false, 'misses' => 0, 'jit' => false];
        }
        return [
            'enabled' => $status['opcache_enabled'],
            'misses' => $status['opcache_statistics']['misses'],
            'jit' => $status['jit']['enabled'],
        ];
    }
}
