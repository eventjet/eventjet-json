<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use RuntimeException;

use function array_map;
use function sprintf;
use function str_replace;

use const PHP_INT_MAX;

/**
 * Number literals that PHP and PHPStan read differently are rejected wherever they appear, as are integer range bounds
 * that are not plain decimal integers.
 *
 * @internal
 */
final class NumberLiteralErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::declarations() as [$native, $declaration, $literal]) {
            foreach (['param', 'var'] as $tag) {
                yield 'unsupported number literal ' . $declaration . ' in ' . $tag => self::error(
                    $native,
                    $declaration,
                    $tag,
                    $literal,
                );
            }
        }
    }

    /**
     * @return array{string, class-string, string, int}
     * @throws RuntimeException
     */
    public static function error(string $native, string $declaration, string $tag, string $literal): array
    {
        $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
        return [
            '{}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value uses unsupported number literal '
                . str_replace('_', replace: '', subject: $literal)
                . ', which PHP and PHPStan can read differently. Use a plain decimal number, such as 42, -7, or 1.5.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
    }

    /** Pairs of a native type and a declaration with `%s` where the literal goes. */
    private const array LITERAL_DECLARATIONS = [
        ['int',       '%s'],
        ['array',     'list<%s>'],
        ['array',     "list<'a'|%s>"],
        ['array',     'non-empty-array<string, list<int|%s>>'],
        ['array',     'array{int, %s}'],
        ['array|int', 'list<int>|%s'],
    ];

    private const array KEY_DECLARATIONS = [['array', 'array{%s: int}']];

    private const array BOUND_DECLARATIONS = [
        ['int',   'int<%s, max>'],
        ['int',   'int<min, %s>'],
        ['?int',  'int<0, %s>|null'],
        ['array', 'list<int<%s, 10>>'],
    ];

    /** @return iterable<array{string, string, string}> */
    private static function declarations(): iterable
    {
        $unsigned = ['0x2a', '0X2A', '0b101', '0B1', '0o52', '0O7', '052', '00', '09'];
        $integers = [
            ...$unsigned,
            ...array_map(static fn(string $integer): string => '-' . $integer, $unsigned),
            ...array_map(static fn(string $integer): string => '+' . $integer, $unsigned),
            '0x2_a',
            '0_7',
            (string) PHP_INT_MAX . '0',
            '9223372036854775808',
            '-9223372036854775809',
        ];
        $groups = [
            [self::LITERAL_DECLARATIONS, [...$integers, '1e999', '-1E999', '1_0e99_9', '.1e999']],
            [self::KEY_DECLARATIONS, $integers],
            [self::BOUND_DECLARATIONS, ['0x10', '+5', '+0', '052', '1.5', '-1e3', '99999999999999999999', '1e999']],
        ];
        foreach ($groups as [$declarations, $literals]) {
            foreach ($literals as $literal) {
                foreach ($declarations as [$native, $declaration]) {
                    yield [$native, sprintf($declaration, $literal), $literal];
                }
            }
        }
    }
}
