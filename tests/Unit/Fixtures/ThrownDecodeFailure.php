<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

use Closure;
use Eventjet\Json\Internal\Error\DecodeFailure;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

/** @internal */
final class ThrownDecodeFailure
{
    /**
     * Returns the error that the action throws, so that a test can check it and go on.
     *
     * @param Closure(): mixed $action
     * @throws AssertionFailedError If the action throws no DecodeFailure.
     */
    public static function of(Closure $action): DecodeFailure
    {
        try {
            $action();
        } catch (DecodeFailure $error) {
            return $error;
        }
        Assert::fail('Expected a DecodeFailure.');
    }

    /**
     * Returns the result of the action, or the error that it throws, as Json::decode() does.
     *
     * @template T
     * @param Closure(): T $action
     * @return T|DecodeFailure
     */
    public static function orResult(Closure $action): mixed
    {
        try {
            return $action();
        } catch (DecodeFailure $error) {
            return $error;
        }
    }
}
