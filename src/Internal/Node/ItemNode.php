<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Stringable;

/**
 * A node that can be a collection item. Its string form is the PHPDoc type that collection errors report.
 *
 * @internal
 */
interface ItemNode extends Node, Stringable {}
