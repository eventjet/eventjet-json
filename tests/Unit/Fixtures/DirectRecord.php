<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

/** @api Constructed dynamically by direct-plan tests. */
final class DirectRecord
{
    public static int $calls = 0;

    public function __construct(
        public string $name,
        public int $id,
        public float $amount,
        public bool $active,
        public string|null $note,
    ) {
        ++self::$calls;
    }

    public static function constructionCount(): int
    {
        return self::$calls;
    }
}
