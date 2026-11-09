<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationFailure;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;

use function array_filter;
use function array_keys;
use function count;
use function in_array;

use const PHP_INT_MAX;

/**
 * A failure inside `not`, `if` or `contains` never counts. A failure inside `anyOf` or `oneOf` counts only together
 * with the failure of that keyword, and then every branch has failed.
 *
 * @internal
 */
final class ApplicatorFailureLaw
{
    /**
     * @param list<ValidationFailure> $failures All failures of the value.
     * @throws \PHPUnit\Framework\Exception
     * @throws InvalidArgumentException
     */
    public static function assertHolds(mixed $schema, ValidationFailure $failure, array $failures): void
    {
        $path = KeywordPath::of($failure->keywordLocation);
        $firstReference = $path->firstReference() ?? PHP_INT_MAX;
        $last = count($path->parts) - 1;
        foreach ($path->keywords as $position) {
            $keyword = $path->parts[$position] ?? '';
            $inside = $position < $last;
            $neverCounts = $inside && in_array($keyword, ['not', 'if', 'contains'], strict: true);
            Assert::assertFalse($neverCounts, (string) $failure . ' should not count.');
            $branches = $inside && in_array($keyword, ['anyOf', 'oneOf'], strict: true);
            if (!$branches) {
                continue;
            }
            $applicator = $path->upTo($position);
            $parent = self::parent($failure, JsonPointer::format($applicator), $failures);
            Assert::assertNotNull($parent, (string) $failure . ' counts although "' . $keyword . '" matches.');
            if ($position < $firstReference) {
                self::assertEveryBranchFailed($schema, $applicator, $parent, $failures);
            }
        }
    }

    /** @param list<ValidationFailure> $failures */
    private static function parent(
        ValidationFailure $failure,
        string $applicator,
        array $failures,
    ): ValidationFailure|null {
        foreach ($failures as $candidate) {
            $isParent =
                $candidate->keywordLocation === $applicator
                && JsonPointer::within($failure->instanceLocation, $candidate->instanceLocation);
            if ($isParent) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * @param list<string> $applicator
     * @param list<ValidationFailure> $failures
     * @throws \PHPUnit\Framework\Exception
     */
    private static function assertEveryBranchFailed(
        mixed $schema,
        array $applicator,
        ValidationFailure $parent,
        array $failures,
    ): void {
        /** @var mixed $branches */
        $branches = JsonPointer::find($schema, $applicator)[0] ?? null;
        Assert::assertIsArray($branches);
        foreach (array_keys($branches) as $index) {
            $branch = JsonPointer::format([...$applicator, (string) $index]);
            $failed = array_filter(
                $failures,
                static fn(ValidationFailure $candidate): bool => (
                    JsonPointer::within($candidate->instanceLocation, $parent->instanceLocation)
                    && JsonPointer::within($candidate->keywordLocation, $branch)
                ),
            );
            Assert::assertNotSame([], $failed, $branch . ' has no failure, but ' . (string) $parent . ' says so.');
        }
    }
}
