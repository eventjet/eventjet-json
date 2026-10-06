<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

/** @internal */
final class CollectionUnionEnumErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::types() as [$type, $value, $reason]) {
            foreach (CollectionUnionCases::shapes($type) as $shape => [$native, $declaration]) {
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                    $map = $shape === 'map' || $shape === 'ArrayObject';
                    $json = '{"value":' . ($map ? '{"01":' . $value . '}' : '[' . $value . ']') . '}';
                    $path = $map ? 'value[01]' : 'value[0]';
                    yield 'collection enum union ' . $declaration . $tag => [
                        $json,
                        $class,
                        'Could not create ' . $class . ' from the JSON object: Field ' . $path . $reason,
                        3,
                    ];
                }
            }
        }
    }

    /** @return iterable<array{string, string, string}> */
    private static function types(): iterable
    {
        yield [
            '\\' . IntBackedStatus::class . '|float',
            '42',
            ' uses backed enum ' . IntBackedStatus::class . ', which has no case with backing value 42.',
        ];
        yield [
            '\\' . StringBackedStatus::class . '|int',
            '"unknown"',
            ' uses backed enum ' . StringBackedStatus::class . ", which has no case with backing value 'unknown'.",
        ];
        yield [
            '\\' . StringBackedStatus::class . '|\\' . StringBackedOutcome::class . '|null',
            '"unknown"',
            ' uses backed enum union '
                . StringBackedOutcome::class
                . '|'
                . StringBackedStatus::class
                . ", which has no case with backing value 'unknown'.",
        ];
    }
}
