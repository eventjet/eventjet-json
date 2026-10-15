<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Error;

use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use PropertyHookType;
use ReflectionProperty;
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

    /**
     * Returns the error for a property assignment that threw. Values match the declared type, so without a set hook,
     * PHP refused the assignment itself.
     */
    public static function assignmentThrew(ReflectionProperty $property, Throwable $error): DecodeError
    {
        $hooked = $property->hasHook(PropertyHookType::Set);
        return $hooked
            ? DecodeError::thrown(DecodeErrorKind::ConstructorFailure, $error)
            : UnsupportedTarget::unassignableProperty($error);
    }
}
