<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

/**
 * A value of the JSON Schema keyword `type`.
 *
 * @api
 */
enum Type: string
{
    case Array = 'array';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Null = 'null';
    case Number = 'number';
    case Object = 'object';
    case String = 'string';
}
