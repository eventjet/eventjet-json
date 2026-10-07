<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use ReflectionException;
use RuntimeException;

/** @internal */
final class NonEncodableCollectionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        $enum = '\\' . NonBackedStatus::class;
        foreach (NonEncodableUnionInputs::domains() as $supported => $values) {
            foreach ([$enum, 'resource', 'open-resource', 'closed-resource', $enum . '|resource'] as $excluded) {
                $union = $excluded . '|' . $supported;
                foreach (CollectionUnionCases::shapes($union) as $shape => [$native, $declaration]) {
                    foreach (['param', 'var'] as $tag) {
                        foreach (CollectionUnionInputs::collections($shape, $values) as $index => $value) {
                            yield 'collection non-encodable union ' . $declaration . $tag . $index => [
                                NonEncodableUnionInputs::object($native, $declaration, $tag, $value),
                            ];
                        }
                    }
                }
            }
        }
    }
}
