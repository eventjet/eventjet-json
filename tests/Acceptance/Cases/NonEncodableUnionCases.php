<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use ReflectionException;
use RuntimeException;

/** @internal */
final class NonEncodableUnionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'non-backed enum alongside string' => [new NonBackedEnumUnionField('supported scalar')];
        yield from NonEncodableCollectionCases::objects();
        $enum = '\\' . NonBackedStatus::class;
        foreach (NonEncodableUnionInputs::nativeDomains() as $union => $values) {
            foreach (['param', 'var'] as $tag) {
                foreach ($values as $index => $value) {
                    yield 'native non-encodable union ' . $union . $tag . $index => [
                        NonEncodableUnionInputs::object($union, '', $tag, $value),
                    ];
                }
            }
        }
        foreach (['param', 'var'] as $tag) {
            yield 'nullable non-backed named type ' . $tag => [NonEncodableUnionInputs::object(
                '?' . $enum,
                '',
                $tag,
                null,
            )];
            foreach ([[], [1, 2], 'text', null] as $index => $value) {
                yield 'non-backed collection field union ' . $tag . $index => [
                    NonEncodableUnionInputs::object(
                        'array|' . $enum . '|string|null',
                        'list<int>|' . $enum . '|string|null',
                        $tag,
                        $value,
                    ),
                ];
            }
            foreach ([[], [null], [[1, 2]], [new Coordinates(1.0, 2.0)]] as $index => $value) {
                yield 'nested non-encodable union ' . $tag . $index => [
                    NonEncodableUnionInputs::object(
                        'array',
                        'list<' . $enum . '|resource|list<int>|\\' . Coordinates::class . '|null>',
                        $tag,
                        $value,
                    ),
                ];
            }
            yield 'nested nullable resource map ' . $tag => [
                NonEncodableUnionInputs::object(
                    'array',
                    'array{ArrayObject<string, list<resource|null>>}',
                    $tag,
                    [new ArrayObject(['a.b' => [null]])],
                ),
            ];
        }
    }
}
