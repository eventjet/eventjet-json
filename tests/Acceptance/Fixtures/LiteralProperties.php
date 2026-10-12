<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class LiteralProperties
{
    /** @var 'foo'|42 */
    public string|int $value = 'foo';

    /** @var list<'foo'|42|true|false|null> */
    public array $items = [];

    /** @var ArrayObject<string, 'foo'> */
    public ArrayObject $map;

    /** @var array{true, false, -42, '', "a|b"} */
    public array $tuple = [true, false, -42, '', 'a|b'];

    /** @var list<list<42>> */
    public array $nested = [[42]];

    public function __construct()
    {
        /** @var ArrayObject<string, 'foo'> $map */
        $map = new ArrayObject(['key' => 'foo']);
        $this->map = $map;
    }
}
