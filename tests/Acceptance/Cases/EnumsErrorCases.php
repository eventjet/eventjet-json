<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;

/** @internal */
final class EnumsErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'non-backed enum root target' => [
            '{}',
            NonBackedStatus::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus from the JSON object: Target type non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            3,
        ];
        yield 'nullable non-backed enum rejects non-null var1' => [
            '{"value":1}',
            CollectionDeclarationFixture::create('?\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus', '', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    '?\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus',
                    '',
                    'var',
                )
                . ' from the JSON object: Field value must be of type ?Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, int given.',
            3,
        ];
        yield 'string-backed enum rejects null' => [
            '{"stringStatus":null,"nullableStatus":null,"intStatus":1}',
            BackedEnumFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields from the JSON object: Field stringStatus uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which expects a string backing value; null given.',
            3,
        ];
    }
}
