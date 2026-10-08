<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;

use function str_repeat;
use function str_replace;

/** @internal */
final class WarmCollectionErrorCases
{
    /** @return iterable<string, array{string, JsonType<list<mixed>>, string, int}> */
    public static function errors(): iterable
    {
        foreach (CollectionValidationErrorCases::errors() as $name => [$json, $class, $message, $code]) {
            $validItems = 3;
            yield 'warm collection ' . $name . ' after ' . $validItems . ' objects' => [
                '[' . str_repeat('{},', $validItems) . $json . ']',
                JsonType::array($class),
                str_replace('Field ', 'Field [' . $validItems . '].', $message),
                $code,
            ];
        }
    }
}
