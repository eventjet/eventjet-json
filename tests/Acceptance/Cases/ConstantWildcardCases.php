<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;

/**
 * Class constant wildcards, such as `Foo::A_*B`, match constant names, and every matched constant must be a literal.
 *
 * @internal
 */
final class ConstantWildcardCases
{
    /**
     * @return iterable<string, DecodeSuccess>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function successes(): iterable
    {
        yield 'a wildcard between name parts matches the whole name' => DecodeSuccess::roundTrip(CollectionDeclarationFixture::object(
            'int',
            self::constants() . '::A_*B',
            'var',
            1,
        ));
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    public static function failures(): iterable
    {
        yield 'a wildcard between name parts does not match the last part alone' => ParserSyntaxDecodeCases::mismatch(
            'int',
            self::constants() . '::A_*B',
            '2',
            '1',
            'int',
        );
        $declaration = self::constants() . '::C_*';
        $class = CollectionDeclarationFixture::create('int', $declaration, 'var');
        yield 'a wildcard that matches a constant with a non-literal value is unsupported' => [
            '{"value":1}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value uses '
                . $declaration
                . ' in its PHPDoc type, which the decoder cannot resolve. Use a type that the decoder supports, or remove'
                . ' the PHPDoc type to decode the native type.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /** @throws \RuntimeException */
    private static function constants(): string
    {
        return '\\'
        . CollectionNameSource::load(
            'WildcardConstants',
            'final class WildcardConstants { const A_X_B = 1; const B = 2; const C_1 = 1; const C_2 = [1]; }',
        );
    }
}
