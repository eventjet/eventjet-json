<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class EnumsRoundTripCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function objects(): iterable
    {
        foreach ([...IntBackedStatus::cases(), ...StringBackedStatus::cases()] as $case) {
            yield 'backed enum root ' . $case::class . '::' . $case->name => [$case];
        }
        yield 'non-backed enum alongside string' => [new NonBackedEnumUnionField('supported scalar')];
        yield 'nullable non-backed enum accepts null' => [CollectionDeclarationFixture::object(
            '?\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus',
            '',
            'param',
            null,
        )];
        yield 'backed enum fields: Ready, Pending' => [new BackedEnumFields(
            StringBackedStatus::Ready,
            StringBackedStatus::Ready,
            IntBackedStatus::Pending,
        )];
        yield 'nullable backed enum field accepts null' => [new BackedEnumFields(
            StringBackedStatus::Pending,
            null,
            IntBackedStatus::Ready,
        )];
    }
}
