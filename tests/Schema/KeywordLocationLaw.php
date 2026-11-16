<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationFailure;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;

use function array_slice;
use function count;

/**
 * The keyword location of a failure leads through the schema document to the keyword, up to the first reference, and
 * the absolute keyword location names the same keyword: its fragment ends like the keyword location, and isn't empty
 * if the keyword location ends with a keyword.
 *
 * @internal
 */
final class KeywordLocationLaw
{
    /** @throws InvalidArgumentException */
    public static function assertHolds(mixed $schema, ValidationFailure $failure): void
    {
        $path = KeywordPath::of($failure->keywordLocation);
        $found = JsonPointer::find($schema, $path->beforeReferences());
        Assert::assertNotSame([], $found, (string) $failure . ' is not in the schema.');
        $fragment = JsonPointer::parseFragment($failure->absoluteKeywordLocation);
        $named = $fragment !== [] || !$path->endsWithKeyword();
        Assert::assertTrue($named, (string) $failure . ' does not name its keyword absolutely.');
        $tail = $path->afterReferences();
        $agrees = self::endsWith($tail, $fragment) || $path->references() && self::endsWith($fragment, $tail);
        Assert::assertTrue($agrees, (string) $failure . ' names two different keywords.');
    }

    /**
     * @param list<string> $parts
     * @param list<string> $suffix
     * @pure
     */
    private static function endsWith(array $parts, array $suffix): bool
    {
        return array_slice($parts, count($parts) - count($suffix)) === $suffix;
    }
}
