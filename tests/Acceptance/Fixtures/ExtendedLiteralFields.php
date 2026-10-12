<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class ExtendedLiteralFields
{
    public const string WORD_FOO = 'foo';
    public const string WORD_BAR = 'bar';
    public const int NUMBER = 42;
    public const float DECIMAL = 3.25;

    /** @var "foo" */
    public string $doubleQuoted = 'foo';
    /** @var 3.0 */
    public float $wholeFloat = 3.0;
    /** @var self::WORD_* */
    public string $constant = 'bar';
    /** @var list<self::NUMBER|self::DECIMAL|StringBackedStatus::Ready> */
    public array $items = [42, 3.25, StringBackedStatus::Ready];
}
