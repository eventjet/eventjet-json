<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;

/** @internal */
final class NonBackedEnumErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        $fieldTarget = new NonBackedEnumField(NonBackedStatus::Ready);
        $rootMessage = 'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedStatus from the JSON object: Target type non-backed enum Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedStatus, which cannot be decoded because it has no backing value.';
        $fieldMessage = 'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedEnumField from the JSON object: Field status uses non-backed enum Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedStatus, which cannot be decoded because it has no backing value.';

        yield 'non-backed enum root target' => ['{}', NonBackedStatus::class, $rootMessage, 3];
        yield 'non-backed enum field, member present' => [
            '{"status":"ready"}',
            $fieldTarget::class,
            $fieldMessage,
            3,
        ];
        yield 'non-backed enum field, member absent' => ['{}', $fieldTarget::class, $fieldMessage, 3];

        $unionTarget = new NonBackedEnumUnionField('supported scalar');
        $unionMessage =
            'Could not create '
            . $unionTarget::class
            . ' from the JSON object: Field value uses non-backed enum '
            . NonBackedStatus::class
            . ', which cannot be decoded because it has no backing value.';

        yield 'non-backed enum union, scalar value' => [
            '{"value":"supported scalar"}',
            $unionTarget::class,
            $unionMessage,
            3,
        ];
        yield 'non-backed enum union, member absent' => ['{}', $unionTarget::class, $unionMessage, 3];
    }
}
