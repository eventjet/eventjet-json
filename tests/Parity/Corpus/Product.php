<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use function array_shift;

/**
 * Enumerates every combination of one item from each list.
 *
 * @internal
 */
final class Product
{
    /**
     * @param list<string> ...$lists
     * @return iterable<list<string>>
     */
    public static function of(array ...$lists): iterable
    {
        $first = array_shift($lists);
        if ($first === null) {
            yield [];
            return;
        }
        foreach ($first as $item) {
            foreach (self::of(...$lists) as $rest) {
                yield [$item, ...$rest];
            }
        }
    }
}
