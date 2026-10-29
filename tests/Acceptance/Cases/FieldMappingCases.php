<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ManualMappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDefaults;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDocument;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedOrdinaryConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedSpecialNames;

/** @internal */
final class FieldMappingCases
{
    /** @return iterable<string, array{object}> */
    public static function objects(): iterable
    {
        foreach (['', '#/$defs/person', '0', 'é/日本語'] as $ref) {
            yield 'mapped reference ' . $ref => [new MappedReference($ref)];
            yield 'manual mapped serializer ' . $ref => [new ManualMappedReference($ref)];
            yield 'ordinary constructor mapping ' . $ref => [new MappedOrdinaryConstructor($ref)];
            yield 'nested mapped collections ' . $ref => [new MappedDocument(
                'urn:example',
                [new MappedReference($ref)],
                new MappedReference($ref),
            )];
        }
        yield 'empty mapped list and scalar union' => [new MappedDocument('urn:example', [], 'literal')];
        yield 'mapped collection with nullable union' => [new MappedDocument('urn:example', [], null)];
        yield 'empty numeric and swapped mappings' => [new MappedSpecialNames('zero', 'empty', 'A', 'B')];
        yield 'uninitialized mapped public property' => [new MappedProperties()];
        $properties = new MappedProperties();
        $properties->anchor = 'node';
        $properties->ref = null;
        $properties->uninitialized = 'present';
        $properties->references = [new MappedReference('#/target')];
        yield 'inherited and converted mapped properties' => [$properties];
    }

    /** @return iterable<string, array{string, object}> */
    public static function decoded(): iterable
    {
        yield 'mapped defaults' => ['{}', new MappedDefaults()];
        yield 'mixed mapped and ordinary constructor fields' => [
            '{"$ref":null,"count":9}',
            new MappedDefaults(ref: null, count: 9),
        ];
        yield 'PHP names are not aliases of mapped constructor fields' => [
            '{"ref":"ignored","$ref":"accepted"}',
            new MappedDefaults(ref: 'accepted'),
        ];
        yield 'mapped constructor field missing despite PHP name' => ['{"ref":"ignored"}', new MappedDefaults()];
        $properties = new MappedProperties();
        $properties->ref = 'accepted';
        yield 'PHP names are not aliases of mapped public properties' => [
            '{"ref":"ignored","$ref":"accepted"}',
            $properties,
        ];
        yield 'mapped numeric input name' => [
            '{"0":"zero","":"empty","second":"A","first":"B"}',
            new MappedSpecialNames('zero', 'empty', 'A', 'B'),
        ];
    }
}
