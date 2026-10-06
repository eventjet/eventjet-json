<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;

use function str_repeat;

/** @internal */
final class ParserDepthCases
{
    /** @return iterable<string, array{string, PhpDocType|null}> */
    public static function types(): iterable
    {
        foreach ([0, 1, 2, 62, 63, 64, 65] as $depth) {
            $tree = new PhpDocType('int');
            for ($level = 0; $level < $depth; $level++) {
                $tree = new PhpDocType('list', [$tree]);
            }
            yield 'depth ' . $depth => [
                str_repeat('list<', $depth) . 'int' . str_repeat('>', $depth),
                $depth < 64 ? $tree : null,
            ];
        }

        foreach ([1, 2, 62, 63, 64, 65] as $depth) {
            $tree = new PhpDocType('int');
            $source = 'int';
            for ($level = 0; $level < $depth; $level++) {
                $tree = new PhpDocType('array{}', entries: [new PhpDocTupleEntry($tree, key: '0', optional: true)]);
                $source = 'array{0?: ' . $source . '}';
            }
            yield 'tuple depth ' . $depth => [$source, $depth < 64 ? $tree : null];
        }
    }
}
