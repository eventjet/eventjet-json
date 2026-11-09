<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

/**
 * What went wrong when validating a value.
 *
 * @api
 */
enum ValidationErrorKind
{
    /** The value is JSON, but it doesn't match the schema. */
    case InvalidValue;

    /**
     * json_encode() can't write the value, for example because it holds a closure, a float that isn't finite, a string
     * that isn't valid UTF-8, or levels nested more than 512 deep.
     */
    case UnsupportedValue;

    /**
     * PCRE gave up on a regex of the schema, for example at its backtrack limit, so the validator can't tell whether
     * the value matches. `not` and the other applicators can't turn this into a match.
     */
    case RegexLimit;
}
