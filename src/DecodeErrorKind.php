<?php

declare(strict_types=1);

namespace Eventjet\Json;

/**
 * What went wrong when decoding. The first three kinds are caused by the input, the others by the target class.
 *
 * @api
 */
enum DecodeErrorKind
{
    /** The input could not be parsed as JSON. */
    case InvalidJson;

    /** The JSON root does not have the type that the target needs, such as an object for a class. */
    case UnexpectedRootValue;

    /** A JSON value does not match its declared type, or a required member is missing. */
    case InvalidValue;

    /** The target class cannot be decoded, whatever the input. */
    case UnsupportedDeclaration;

    /** The target's constructor, or a set hook of one of its public properties, threw. */
    case ConstructorFailure;
}
