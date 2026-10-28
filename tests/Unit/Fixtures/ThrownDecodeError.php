<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

use Closure;
use Eventjet\Json\DecodeError;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

/** @internal */
final class ThrownDecodeError
{
    /**
     * Returns the error that the action throws, so that a test can check it and go on.
     *
     * @param Closure(): mixed $action
     * @throws AssertionFailedError If the action throws no DecodeError.
     */
    public static function of(Closure $action): DecodeError
    {
        try {
            $action();
        } catch (DecodeError $error) {
            return $error;
        }
        Assert::fail('Expected a DecodeError.');
    }

    /**
     * Returns the result of the action, or the error that it throws, as Json::decode() does.
     *
     * @template T
     * @param Closure(): T $action
     * @return T|DecodeError
     */
    public static function orResult(Closure $action): mixed
    {
        try {
            return $action();
        } catch (DecodeError $error) {
            return $error;
        }
    }
}
