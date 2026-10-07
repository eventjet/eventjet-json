<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use Eventjet\Json\DecodeError;

/**
 * @internal
 * @template T
 */
final class MetadataCache
{
    /** @var array<string, T> */
    private array $values = [];

    /**
     * @param Closure(): T $load
     * @return T
     * @phpstan-impure
     */
    public function resolve(string $key, Closure $load): mixed
    {
        $cached = $this->values[$key] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $value = $load();
        if ($value !== null && !$value instanceof DecodeError) {
            $this->values[$key] = $value;
        }

        return $value;
    }
}
