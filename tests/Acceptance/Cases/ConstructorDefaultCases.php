<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\CollectionUnionDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\DefaultedConstructorFields;
use Eventjet\Json\Test\Acceptance\Fixtures\OmittedInitializedReadonlyPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\OmittedPublicProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\StringCollectionValidationFields;
use InvalidArgumentException;
use JsonException;
use ReflectionException;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class ConstructorDefaultCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, object}>
     * @throws JsonException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function objects(): iterable
    {
        yield from FieldMappingCases::decoded();
        yield from NumericBoundaryRoundTripCases::decoded();
        yield from JsonValueCases::decoded();
        yield 'omitted collection union uses constructor default' => ['{}', new CollectionUnionDefaults()];
        yield 'null collection union overrides constructor default' => [
            '{"value":null}',
            new CollectionUnionDefaults(null),
        ];
        $emptyMap = new CollectionUnionDefaults('');
        $emptyMap->map = new ArrayObject();
        yield 'empty map collection union overrides property default' => ['{"value":"","map":{}}', $emptyMap];
        yield 'json_encode() drops an ArrayObject key starting with a null byte' => [
            json_encode(new StringCollectionValidationFields(objectMap: new ArrayObject([
                "\0hidden" => 'ready',
            ])), JSON_THROW_ON_ERROR),
            new StringCollectionValidationFields(),
        ];

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

        yield 'null satisfies a nullable parameter whose class is not loaded' => [
            '{"value":null}',
            CollectionDeclarationFixture::object('?UnloadedConstructorClass', '', 'param', null),
        ];

        yield 'null satisfies a nullable public property whose class is not loaded' => [
            '{"value":null}',
            CollectionDeclarationFixture::object('?UnloadedPropertyClass', '', 'var', null),
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
