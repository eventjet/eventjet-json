<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\SerializedJson;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

use function sprintf;

/**
 * Declarations and inputs at the limits of JsonValue: unions that it cannot join, and input nested to the depth that
 * json_encode() can write.
 *
 * @internal
 */
final class JsonValueLimits
{
    /**
     * Class bodies that declare JsonValue in a union, with the path of the field that errors name.
     *
     * @return array<string, array{string, string}>
     */
    public static function unions(): array
    {
        $jsonValue = '\\' . JsonValue::class;
        return [
            'with a scalar' => ['public function __construct(public ' . $jsonValue . '|string $value) {}', 'value'],
            'with a scalar and null' => ['public ' . $jsonValue . '|int|null $value = null;', 'value'],
            'with a float' => ['public ' . $jsonValue . '|float $value;', 'value'],
            'with a literal' => ['/** @var ' . $jsonValue . '|true */ public ' . $jsonValue . '|bool $value;', 'value'],
            'with a backed enum' => [
                'public ' . $jsonValue . '|\\' . StringBackedStatus::class . ' $value;',
                'value',
            ],
            'with a class' => ['public ' . $jsonValue . '|\\' . Coordinates::class . ' $value;', 'value'],
            'with null before it as a list item' => [
                '/** @var list<null|' . $jsonValue . '> */ public array $value = [];',
                'value',
            ],
            'with null as a list item' => [
                '/** @var list<' . $jsonValue . '|null> */ public array $value = [];',
                'value',
            ],
            'with a scalar as a map value' => [
                '/** @var \\ArrayObject<string, ' . $jsonValue . '|string> */ public \\ArrayObject $value;',
                'value',
            ],
            'with null in a tuple position' => [
                '/** @var array{' . $jsonValue . '|null} */ public array $value;',
                'value[0]',
            ],
        ];
    }

    /**
     * Inputs for the JsonValue constructor nested the given number of levels deep, keyed by what they nest. Each
     * serializer of a list adds two levels.
     *
     * @return array<string, string>
     */
    public static function nested(int $depth): array
    {
        $jsonValue = '\\' . JsonValue::class;
        $list = 'new ' . $jsonValue . '([$value])';
        $inputs = [];
        foreach ([
            'lists' => ['[$value]', 'null', 0],
            'objects' => ['["a" => $value]', 'null', 0],
            'JsonValue objects of objects' => ['new ' . $jsonValue . '(["a" => $value])', 'null', 0],
            'JsonValue lists around null' => [$list, 'null', 0],
            'JsonValue lists around a float' => [$list, '1.5', 0],
            'JsonValue lists around a string' => [$list, '"a"', 0],
            'JsonValue lists around a backed enum' => [$list, '\\' . StringBackedStatus::class . '::Ready', 0],
            'JsonValue lists around an empty list' => [$list, '[]', 1],
            'JsonValue lists around an empty object' => [$list, 'new \\stdClass()', 1],
            'JsonValue lists around a nested empty list' => [$list, '(new ' . $jsonValue . '([[]]))->value()[0]', 1],
            'JsonValue lists around a serialized empty list' => [
                $list,
                'new ' . $jsonValue . '(new \\' . SerializedJson::class . '([]))',
                1,
            ],
        ] as $label => [$wrap, $start, $startDepth]) {
            $inputs[$label] = self::reduce($depth - $startDepth, $wrap, $start);
        }
        $inputs['serializers of lists'] = self::reduce(
            $depth >> 1,
            'new \\' . SerializedJson::class . '([$value])',
            ($depth % 2) === 1 ? '[]' : 'null',
        );
        $inputs['a copy of JsonValue lists'] = 'new ' . $jsonValue . '(' . self::reduce($depth, $list, 'null') . ')';
        return $inputs;
    }

    private static function reduce(int $times, string $wrap, string $start): string
    {
        return sprintf('array_reduce(range(1, %d), static fn(mixed $value): mixed => %s, %s)', $times, $wrap, $start);
    }
}
