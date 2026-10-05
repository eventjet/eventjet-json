<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use JsonException;
use RuntimeException;

use function count;
use function get_debug_type;
use function is_object;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class IndexedTupleErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            $class = CollectionDeclarationFixture::create(
                'array',
                'array{0: int, 1: string, 2?: bool, 3?: float}',
                $tag,
            );
            $expected = 'array{int, string, 2?: bool, 3?: float}';

            foreach ([[], [1], [1, 'text', true, 3.0, 5]] as $value) {
                yield 'indexed tuple length ' . $tag . count($value) => self::error(
                    $class,
                    $value,
                    'must be of type ' . $expected . ' with between 2 and 4 items, ' . count($value) . ' given.',
                );
            }

            foreach ([
                null,
                1,
                'text',
                false,
                (object) [],
                (object) [1, 'text'],
                (object) [0 => 1, 1 => 'text', 3 => 3.0],
            ] as $index => $value) {
                $expectedType = is_object($value) ? $expected : 'array';
                yield 'indexed tuple shape ' . $tag . $index => self::error(
                    $class,
                    $value,
                    'must be of type ' . $expectedType . ', ' . get_debug_type($value) . ' given.',
                );
            }

            yield from self::scalarErrors($tag);
            yield from self::enumErrors($tag);
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function scalarErrors(string $tag): iterable
    {
        foreach ([
            'int' => '1',
            'string' => 1,
            'float' => '3.0',
            'bool' => 1,
            '\\' . Person::class => [],
        ] as $type => $wrong) {
            $class = CollectionDeclarationFixture::create('array', 'array{0?: ' . $type . '}', $tag);
            $expectedType = $type === '\\' . Person::class ? Person::class : $type;
            yield 'optional tuple item ' . $tag . $type => self::error(
                $class,
                [$wrong],
                'must be of type ' . $expectedType . ', ' . get_debug_type($wrong) . ' given.',
                '[0]',
            );
            yield 'optional tuple null ' . $tag . $type => self::error(
                $class,
                [null],
                'must be of type ' . $expectedType . ', null given.',
                '[0]',
            );
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function enumErrors(string $tag): iterable
    {
        foreach ([
            StringBackedStatus::class => ['unknown', 1, 'string'],
            IntBackedStatus::class => [99, '1', 'int'],
        ] as $type => [$unknown, $wrong, $backing]) {
            $class = CollectionDeclarationFixture::create('array', 'array{0?: \\' . $type . '}', $tag);
            $literal = $unknown === 'unknown' ? "'unknown'" : '99';
            yield 'optional tuple enum case ' . $tag . $type => self::error(
                $class,
                [$unknown],
                'uses backed enum ' . $type . ', which has no case with backing value ' . $literal . '.',
                '[0]',
            );
            yield 'optional tuple enum backing ' . $tag . $type => self::error(
                $class,
                [$wrong],
                'uses backed enum '
                . $type
                . ', which expects a '
                . $backing
                . ' backing value; '
                . get_debug_type($wrong)
                . ' given.',
                '[0]',
            );
        }
    }

    /**
     * @param class-string $class
     * @return array{string, class-string, string, int}
     * @throws JsonException
     */
    private static function error(string $class, mixed $value, string $detail, string $position = ''): array
    {
        return [
            json_encode(['value' => $value], JSON_THROW_ON_ERROR),
            $class,
            'Could not create ' . $class . ' from the JSON object: Field value' . $position . ' ' . $detail,
            3,
        ];
    }
}
