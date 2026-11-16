<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

use Closure;
use Eventjet\Json\DecodeError;

/** @internal */
final class ThrownDecodeError
{
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
