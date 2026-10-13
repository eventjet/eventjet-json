<?php

declare(strict_types=1);

namespace Eventjet\Json;

/**
 * What went wrong when decoding. The first three kinds are caused by the input, the others by the target class.
 *
 * @api
 */
enum DecodeErrorKind: int
{
    /** The input could not be parsed as JSON. */
    case InvalidJson = 1;

    /** The JSON root is not the object or array that the target needs. */
    case UnexpectedRootValue = 2;

    /** A JSON value does not match its declared type, or a required member is missing. */
    case InvalidValue = 3;

    /** The target class cannot be decoded, whatever the input. */
    case UnsupportedDeclaration = 4;

    /** The target's constructor, or a set hook of one of its public properties, threw. */
    case ConstructorFailure = 5;
}
