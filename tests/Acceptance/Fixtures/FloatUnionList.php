<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use UnexpectedValueException;

use function array_all;
use function is_float;

final readonly class FloatUnionList
{
    /**
     * @param list<float|null> $value
     * @throws UnexpectedValueException
     */
    public function __construct(
        public array $value,
    ) {
        $valid = array_all($value, self::isFloatOrNull(...));
        if (!$valid) {
            throw new UnexpectedValueException('Expected float or null items before construction.');
        }
    }

    private static function isFloatOrNull(mixed $value): bool
    {
        return $value === null || is_float($value);
    }
}
