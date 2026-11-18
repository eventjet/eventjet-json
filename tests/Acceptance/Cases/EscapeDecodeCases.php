<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;

use function json_encode;

/**
 * String literals with Unicode escapes at the boundaries of each UTF-8 length, of surrogates, and beyond the last code
 * point, and with hexadecimal escapes, which are bytes. A string of the wrong value makes the decoder print the bytes
 * that it read.
 *
 * @internal
 */
final class EscapeDecodeCases
{
    /**
     * @return iterable<string, DecodeSuccess>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function successes(): iterable
    {
        foreach (self::escapes() as $label => [$escape, $bytes]) {
            $encodable = json_encode($bytes) !== false;
            if (!$encodable) {
                continue;
            }
            yield $label => DecodeSuccess::roundTrip(CollectionDeclarationFixture::object(
                'string',
                $escape,
                'var',
                $bytes,
            ));
        }
    }

    /** @return iterable<string, array{string, class-string, string, DecodeErrorKind}> */
    public static function failures(): iterable
    {
        foreach (self::escapes() as $label => [$escape, $bytes]) {
            yield $label . ' is printed' => ParserSyntaxDecodeCases::mismatch(
                'string',
                $escape,
                '"x"',
                '"' . $bytes . '"',
                'string',
            );
        }
    }

    /** @return iterable<string, array{string, string}> */
    private static function escapes(): iterable
    {
        foreach ([
            '0' => "\0",
            '0041' => 'A',
            '7f' => "\x7F",
            '80' => "\u{80}",
            '100' => "\u{100}",
            '7ff' => "\u{7ff}",
            '800' => "\u{800}",
            'd7ff' => "\u{d7ff}",
            'd800' => "\xED\xA0\x80",
            'dfff' => "\xED\xBF\xBF",
            'e000' => "\u{e000}",
            'ffff' => "\u{ffff}",
            '10000' => "\u{10000}",
            '103ff' => "\u{103ff}",
            '10400' => "\u{10400}",
            '10ffff' => "\u{10ffff}",
            '110000' => "\xF4\x90\x80\x80",
            '1fffff' => "\xF7\xBF\xBF\xBF",
            '200000' => "\u{fffd}",
            'ffffffff' => "\u{fffd}",
            'ffffffffffffffffffffffff' => "\0",
        ] as $code => $bytes) {
            yield 'the Unicode escape ' . $code => ['"\\u{' . $code . '}"', $bytes];
        }
        foreach ([
            '\x41' => 'A',
            '\X41' => 'A',
            '\x7' => "\x07",
            '\xfF' => "\xFF",
            '\x1g' => "\x01g",
        ] as $escape => $bytes) {
            yield 'the hexadecimal escape ' . $escape => ['"' . $escape . '"', $bytes];
        }
    }
}
