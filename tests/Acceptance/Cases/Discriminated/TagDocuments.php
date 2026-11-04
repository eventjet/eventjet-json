<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Aliases;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Close;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Dot;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\EnumTagged;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Label;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Line;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Opcode;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Ping;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Pong;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\ShapeKind;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Square;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Triangle;

use function count;
use function sprintf;

/** @internal */
final class TagDocuments
{
    public static function enumTagged(Document $document): EnumTagged
    {
        $dice = $document->dice;
        $marks = [];
        $tag = Tag::of('kind', "'dot', 'line', 'label'", ShapeKind::Polygon);
        for ($index = $dice->int(1, 3); $index > 0; --$index) {
            $mark = $dice->pick([new Dot(ShapeKind::Dot, 1), new Line(ShapeKind::Line, 2), new Label('label', 'text')]);
            $undeclared = $mark instanceof Label ? Tag::of('kind', $tag->allowed, 'unknown') : $tag;
            $marks[] = $document->tagged(EnumTagged::class, sprintf('marks[%d]', count($marks)), $mark, $undeclared);
        }
        $frames = [];
        $tag = Tag::of('op', '1, 2, 8', 99);
        for ($index = $dice->int(1, 3); $index > 0; --$index) {
            $sequence = $dice->int(0, 99);
            $frame = $dice->pick([new Ping(1, $sequence), new Pong(2, $sequence), new Close(Opcode::Close, 'done')]);
            $undeclared = $frame instanceof Close ? Tag::of('op', $tag->allowed, Opcode::Unused) : $tag;
            $frames[] = $document->tagged(
                EnumTagged::class,
                sprintf('frames[%d]', count($frames)),
                $frame,
                $undeclared,
            );
        }
        return new EnumTagged($marks, $frames);
    }

    public static function aliases(Document $document): Aliases
    {
        $dice = $document->dice;
        $tag = Tag::of('type', "'square', 'box', 'triangle', 'tri', 'delta'", 'box ');
        $shapes = [];
        for ($index = $dice->int(1, 3); $index > 0; --$index) {
            $square = $dice->chance(50);
            $shape = $square
                ? new Square($dice->pick(['square', 'box']), $dice->int(1, 9))
                : new Triangle($dice->pick(['triangle', 'tri', 'delta']), $dice->int(1, 9));
            $shapes[] = $document->tagged(Aliases::class, sprintf('shapes[%d]', count($shapes)), $shape, $tag);
        }
        return new Aliases($shapes);
    }
}
