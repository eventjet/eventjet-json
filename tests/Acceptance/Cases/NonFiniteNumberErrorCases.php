<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Random\Engine\Mt19937;
use Random\Randomizer;

use function sprintf;
use function str_repeat;
use function str_starts_with;

/**
 * JSON numbers beyond the float range decode as INF or -INF, which `json_encode()` cannot write, so every place that
 * accepts a float rejects them.
 *
 * @internal
 */
final class NonFiniteNumberErrorCases
{
    /**
     * Each declaration with the JSON value that holds the number at `%s`, and the path of the number.
     */
    private const array POSITIONS = [
        ['float',             '',                                  '%s',             'value'],
        ['?float',            '',                                  '%s',             'value'],
        ['int|float',         '',                                  '%s',             'value'],
        ['float|string|null', '',                                  '%s',             'value'],
        ['array',             'list<float>',                       '[1.5,%s]',       'value[1]'],
        ['array',             'non-empty-list<float|null>',        '[null,%s]',      'value[1]'],
        ['array',             'list<int|float>',                   '[1,%s]',         'value[1]'],
        ['array',             'list<list<float>>',                 '[[%s]]',         'value[0][0]'],
        ['array',             'non-empty-array<string, float>',    '{"a":1,"b":%s}', 'value[b]'],
        ['ArrayObject',       'ArrayObject<string, float|string>', '{"a":%s}',       'value[a]'],
        ['array',             'array{int, float}',                 '[1,%s]',         'value[1]'],
        ['array',             'array{0: string, 1?: float|null}',  '["a",%s]',       'value[1]'],
    ];

    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::numbers() as $number) {
            $infinity = str_starts_with($number, '-') ? '-INF' : 'INF';
            foreach (self::POSITIONS as [$native, $declaration, $json, $path]) {
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                    $name = sprintf('%s %s %s rejects %s', $tag, $native, $declaration, $number);
                    yield $name => [
                        sprintf('{"value":%s}', sprintf($json, $number)),
                        $class,
                        self::message($class, $path, $infinity),
                        DecodeErrorKind::InvalidValue,
                    ];
                    yield 'cached ' . $name => [
                        sprintf('[{"value":%s},{"value":%s}]', sprintf($json, 1), sprintf($json, $number)),
                        JsonType::array($class),
                        self::message($class, '[1].' . $path, $infinity),
                        DecodeErrorKind::InvalidValue,
                    ];
                }
            }
        }
    }

    /**
     * The numbers just beyond the float range, integers too long for it, and seeded random numbers with exponents
     * beyond it.
     *
     * @return list<string>
     */
    private static function numbers(): array
    {
        $numbers = ['1.7976931348623159e308', '-1.7976931348623159e308', '1' . str_repeat('0', times: 309), '-1e400'];
        $random = new Randomizer(new Mt19937(20_261_102));
        for ($i = 0; $i < 4; $i++) {
            $number = sprintf(
                '%d.%d%s%d',
                $random->getInt(1, 9),
                $random->getInt(0, 999_999),
                ($i % 2) === 0 ? 'e' : 'E+',
                $random->getInt(309, 99_999),
            );
            $numbers[] = $number;
            $numbers[] = '-' . $number;
        }
        return $numbers;
    }

    private static function message(string $class, string $path, string $infinity): string
    {
        return sprintf(
            'Could not create %s from the JSON object: Field %s must be a finite float. The JSON number is outside the PHP float range and decodes as %s, which json_encode() cannot write.',
            $class,
            $path,
            $infinity,
        );
    }
}
