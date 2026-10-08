<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;

/** @internal */
final class TargetsRoundTripCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: list<mixed>|object, 1?: string|null, 2?: \Eventjet\Json\JsonType<list<mixed>|object>|(\Closure(): \Eventjet\Json\JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'nullable non-backed named type param' => [CollectionDeclarationFixture::object(
            '?\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus',
            '',
            'param',
            null,
        )];
        yield 'Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus length 1 depth 1' => [
            [IntBackedStatus::Ready],
            null,
            JsonType::array(IntBackedStatus::class),
        ];
        yield 'InvalidCollection_b3b56e07b6cb572daca43dbd647684d916271d55 batch 0' => [
            [CollectionDeclarationFixture::object('array', 'list<list<int>|non-empty-map<string, bool>|int>', 'param', [
                [],
                [1],
                ['01' => false],
                0,
            ])],
            null,
            JsonType::array(CollectionDeclarationFixture::create(
                'array',
                'list<list<int>|non-empty-map<string, bool>|int>',
                'param',
            )),
        ];
    }
}
