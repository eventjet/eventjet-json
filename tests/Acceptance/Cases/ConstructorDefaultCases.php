<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\DefaultedConstructorFields;
use Eventjet\Json\Test\Acceptance\Fixtures\OmittedInitializedReadonlyPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\OmittedPublicProperties;

/** @internal */
final class ConstructorDefaultCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, DefaultedConstructorFields|OmittedInitializedReadonlyPublicProperty|OmittedPublicProperties}>
     */
    public static function objects(): iterable
    {
        yield 'omitted optional members use constructor defaults' => [
            '{"required":"value"}',
            new DefaultedConstructorFields('value'),
        ];

        yield 'a present optional member overrides its constructor default' => [
            '{"required":"value","label":"custom label"}',
            new DefaultedConstructorFields('value', 'custom label'),
        ];

        yield 'present null overrides a nullable constructor default' => [
            '{"required":"value","nullableCount":null}',
            new DefaultedConstructorFields('value', nullableCount: null),
        ];

        yield 'omitted public properties keep their constructed state' => [
            '{}',
            new OmittedPublicProperties(),
        ];

        yield 'omitted initialized readonly public property keeps its constructed state' => [
            '{}',
            new OmittedInitializedReadonlyPublicProperty(),
        ];
    }
}
