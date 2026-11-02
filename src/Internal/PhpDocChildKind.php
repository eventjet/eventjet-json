<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * The kinds of parts that phpstan/phpdoc-parser 2.x divides a doc comment into.
 *
 * @internal
 */
enum PhpDocChildKind
{
    case Text;

    /** A tag that phpdoc-parser does not know, which the next tag may follow on the same line. */
    case Generic;

    /** A known tag whose value does not match its grammar. */
    case Invalid;

    /** A known tag whose value matches its grammar. */
    case Valid;
}
