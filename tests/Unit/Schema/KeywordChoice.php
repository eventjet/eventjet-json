<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Schema;

use Random\Randomizer;

/** @internal */
enum KeywordChoice
{
    case LeaveOut;
    case Keep;
    case Replace;

    public static function random(Randomizer $random): self
    {
        $index = $random->getInt(0, 2);
        return match ($index) {
            0 => self::LeaveOut,
            1 => self::Keep,
            default => self::Replace,
        };
    }
}
