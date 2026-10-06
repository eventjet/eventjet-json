<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

/** @internal */
final class NestedCollectionShapeErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            'list<list<int>|null>' => 'list<list<int>|null>',
            'list<ArrayObject<string, int>>' => 'list<ArrayObject<string, int>>',
            'list<non-empty-array<string, int>|null>' => 'list<non-empty-array<string, int>|null>',
            'list<non-empty-map<string, int>>' => 'list<non-empty-array<string, int>>',
            'list<non-empty-list<int>>' => 'list<non-empty-list<int>>',
        ] as $type => $expected) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', $type, $tag);
                yield 'nested declaration in root shape error ' . $type . $tag => [
                    '{"value":{}}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value must be of type '
                        . $expected
                        . ', stdClass given.',
                    3,
                ];
            }
        }
    }
}
