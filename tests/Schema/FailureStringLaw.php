<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationFailure;
use PHPUnit\Framework\Assert;

/**
 * A failure reads as its instance location, or "(root)" for the whole value, then its message, then its keyword
 * location in parentheses.
 *
 * @internal
 */
final class FailureStringLaw
{
    public static function assertHolds(ValidationFailure $failure): void
    {
        $where = $failure->instanceLocation === '' ? '(root)' : $failure->instanceLocation;
        Assert::assertSame(
            $where . ': ' . $failure->message . ' (' . $failure->keywordLocation . ')',
            (string) $failure,
        );
    }
}
