<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields as Definitions;

const LITERAL_NUMBER = 42;

final readonly class LiteralConstantFields
{
    public const null NOTHING = null;

    /**
     * @param Definitions::NUMBER $value
     * @param self::NOTHING $nothing
     */
    public function __construct(
        public int $value = 42,
        public null $nothing = null,
    ) {}
}
