<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

enum DecodeErrorKind: int
{
    /** The input could not be parsed as JSON. */
    case InvalidJson = 1;

    /** The decoded root value cannot provide constructor arguments. */
    case UnexpectedRootValue = 2;

    /** The target class rejected the decoded constructor arguments. */
    case CannotInstantiate = 3;
}
