<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use ArrayObject;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Circle;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Drawing;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\MixedValues;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Rectangle;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function count;
use function sprintf;

/** @internal */
final class ShapeDocuments
{
    public static function drawing(Document $document): Drawing
    {
        $dice = $document->dice;
        $shapes = [];
        for ($index = $dice->int(0, 3); $index > 0; --$index) {
            $shapes[] = self::shape($document, Drawing::class, sprintf('shapes[%d]', count($shapes)));
        }
        /** @var ArrayObject<string, Circle|Rectangle> $named */
        $named = new ArrayObject();
        for ($index = $dice->int(0, 2); $index > 0; --$index) {
            $named['n' . $index] = self::shape($document, Drawing::class, 'named[n' . $index . ']');
        }
        $layers = ['layer-0' => self::shape($document, Drawing::class, 'layers[layer-0]')];
        for ($index = $dice->int(0, 1); $index > 0; --$index) {
            $layers['layer-' . $index] = self::shape($document, Drawing::class, 'layers[layer-' . $index . ']');
        }
        $pinned = [self::shape($document, Drawing::class, 'pinned[0]'), $dice->int(-5, 5)];
        $drawing = new Drawing(self::shape($document, Drawing::class, 'main'), $shapes, $named, $layers, $pinned);
        $withBackground = $dice->chance(50);
        $drawing->background = $withBackground ? self::shape($document, Drawing::class, 'background') : null;
        for ($index = $dice->int(0, 2); $index > 0; --$index) {
            $path = sprintf('drafts[%d]', count($drawing->drafts));
            $drawing->drafts[] = self::shape($document, Drawing::class, $path);
        }
        return $drawing;
    }

    public static function mixed(Document $document): MixedValues
    {
        $dice = $document->dice;
        $kind = $dice->int(0, 4);
        $value = match ($kind) {
            0 => self::shape($document, MixedValues::class, 'value'),
            1 => $dice->int(-3, 3),
            2 => 'text',
            3 => [$dice->int(0, 9)],
            default => null,
        };
        $values = [self::shape($document, MixedValues::class, 'values[0]')];
        for ($index = $dice->int(0, 4); $index > 0; --$index) {
            $kind = $dice->int(0, 5);
            $values[] = match ($kind) {
                0 => self::shape($document, MixedValues::class, sprintf('values[%d]', count($values))),
                1 => StringBackedStatus::Pending,
                2 => $dice->int(-3, 3),
                3 => $dice->chance(50),
                4 => [$dice->int(0, 9), $dice->int(0, 9)],
                default => null,
            };
        }
        return new MixedValues($value, $values);
    }

    private static function shape(Document $document, string $container, string $path): Circle|Rectangle
    {
        $dice = $document->dice;
        $round = $dice->chance(50);
        $shape = $round ? new Circle($dice->int(0, 40) / 4) : new Rectangle($dice->int(0, 9), $dice->int(0, 9));
        return $document->tagged($container, $path, $shape, Tag::of('type', "'circle', 'rectangle'", 'unknown'));
    }
}
