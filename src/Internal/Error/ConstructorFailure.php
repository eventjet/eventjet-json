<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Throwable;

/**
 * Errors thrown by the target class itself while the decoder creates an object.
 *
 * @internal
 */
final class ConstructorFailure
{
    public static function constructorThrew(Throwable $error): DecodeError
    {
        return DecodeError::thrown(DecodeErrorKind::ConstructorFailure, $error);
    }

    public static function propertyHookThrew(Throwable $error): DecodeError
    {
        return DecodeError::thrown(DecodeErrorKind::ConstructorFailure, $error);
    }
}
