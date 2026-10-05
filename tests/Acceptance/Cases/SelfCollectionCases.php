<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\InheritedSelfCollection;
use Eventjet\Json\Test\Acceptance\Fixtures\NonFinalSelfCollection;
use Eventjet\Json\Test\Acceptance\Fixtures\SelfCollectionNode;

/** @internal */
final class SelfCollectionCases
{
    /** @return iterable<string, array{object}> */
    public static function objects(): iterable
    {
        $node = new SelfCollectionNode();

        yield 'empty self collections' => [$node];

        for ($depth = 1; $depth <= 4; ++$depth) {
            $parent = new SelfCollectionNode([$node], new ArrayObject(['child' => $node]));
            $parent->publicList = [$node];
            $parent->publicObjectMap = new ArrayObject(['child' => $node]);

            yield 'self collections at depth ' . $depth => [$parent];

            $node = $parent;
        }

        yield from self::branchingObjects();
    }

    /** @return iterable<string, array{SelfCollectionNode}> */
    private static function branchingObjects(): iterable
    {
        $child = new SelfCollectionNode();

        for ($depth = 1; $depth <= 3; ++$depth) {
            $fields = 0;
            do {
                $leaf = new SelfCollectionNode();
                $parent = new SelfCollectionNode(
                    ($fields & 1) === 0 ? [] : [$child, $leaf],
                    new ArrayObject(($fields & 2) === 0 ? [] : ['01' => $leaf, '+1' => $child]),
                );
                $parent->publicList = ($fields & 4) === 0 ? [] : [$leaf, $child];
                $parent->publicObjectMap = new ArrayObject(
                    ($fields & 8) === 0 ? [] : ['1.0' => $child, '1e0' => $leaf],
                );

                yield 'branching self collections ' . $depth . '/' . $fields => [$parent];
                ++$fields;
            } while ($fields < 16);

            $child = $parent;
        }
    }

    /** @return iterable<string, array{string, class-string, string, int}> */
    public static function errors(): iterable
    {
        foreach ([
            'children' => ['[42]', '[0]'],
            'namedChildren' => ['{"child":42}', '[child]'],
            'publicList' => ['[42]', '[0]'],
            'publicObjectMap' => ['{"child":42}', '[child]'],
        ] as $field => [$value, $path]) {
            yield 'self collection invalid item: ' . $field => [
                '{"' . $field . '":' . $value . '}',
                SelfCollectionNode::class,
                'Could not create '
                    . SelfCollectionNode::class
                    . ' from the JSON object: Field '
                    . $field
                    . $path
                    . ' must be of type '
                    . SelfCollectionNode::class
                    . ', int given.',
                3,
            ];
        }

        foreach ([new NonFinalSelfCollection(), new InheritedSelfCollection()] as $target) {
            $class = $target::class;
            foreach (['{}', '{"children":[]}'] as $json) {
                yield 'self resolves the declaring class: ' . $class . $json => [
                    $json,
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field children uses non-final class '
                        . NonFinalSelfCollection::class
                        . '. Values may be subclasses, whose runtime class JSON does not identify.',
                    3,
                ];
            }
        }
    }
}
