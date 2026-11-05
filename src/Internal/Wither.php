<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Schema\Keep;

/** @internal */
final class Wither
{
    /**
     * Returns the new value, or the current one if the new value is `Keep::Current`.
     *
     * @template T
     * @param T|Keep $new
     * @param T $current
     * @return T
     */
    public static function value(mixed $new, mixed $current): mixed
    {
        return $new instanceof Keep ? $current : $new;
    }
}
