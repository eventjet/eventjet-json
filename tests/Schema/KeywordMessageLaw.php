<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationFailure;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use stdClass;

use function array_slice;
use function is_float;
use function is_int;
use function sprintf;

/**
 * A failure of `const` or `enum` says which of the two failed, and a failure of a number limit says which bound the
 * value missed and its limit. In draft 4, `exclusiveMaximum` and `exclusiveMinimum` are booleans that make the
 * `maximum` and `minimum` next to them exclusive.
 *
 * @internal
 */
final class KeywordMessageLaw
{
    /** @throws InvalidArgumentException */
    public static function assertHolds(mixed $schema, ValidationFailure $failure): void
    {
        $path = KeywordPath::of($failure->keywordLocation);
        $keyword = array_slice($path->parts, offset: -1)[0] ?? '';
        /** @var mixed $parent */
        $parent = JsonPointer::find($schema, array_slice($path->parts, offset: 0, length: -1))[0] ?? null;
        if ($path->references() || !$path->endsWithKeyword() || !$parent instanceof stdClass) {
            return;
        }
        $members = (array) $parent;
        /** @var mixed $limit */
        $limit = $members[$keyword] ?? null;
        $exclusive = ($members[$keyword === 'maximum' ? 'exclusiveMaximum' : 'exclusiveMinimum'] ?? null) === true;
        $bound = match ($keyword) {
            'maximum' => $exclusive ? 'less than' : 'at most',
            'exclusiveMaximum' => 'less than',
            'minimum' => $exclusive ? 'greater than' : 'at least',
            'exclusiveMinimum' => 'greater than',
            default => null,
        };
        $expected = match (true) {
            $keyword === 'const' => 'The value must be equal to the constant.',
            $keyword === 'enum' => 'The value must be one of the enumerated values.',
            $bound !== null && (is_int($limit) || is_float($limit)) => sprintf(
                'The value must be %s %s.',
                $bound,
                (string) $limit,
            ),
            default => null,
        };
        if ($expected !== null) {
            Assert::assertSame($expected, $failure->message, (string) $failure);
        }
    }
}
