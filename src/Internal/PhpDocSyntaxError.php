<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Exception;

/**
 * Signals that the tokens do not match the grammar. Parsers backtrack on it often, so one instance is reused: creating
 * an exception records a stack trace, which is the expensive part.
 *
 * @internal
 */
final class PhpDocSyntaxError extends Exception
{
    private static self|null $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self('The PHPDoc does not match the grammar.');
    }
}
