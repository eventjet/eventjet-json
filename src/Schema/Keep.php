<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

/**
 * The default of each parameter of `with()` in the schema classes: the keyword keeps its current value.
 *
 * @api
 */
enum Keep
{
    case Current;
}
