<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Throwable;

use function array_key_exists;

/** @internal */
final class DecodeFailureCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: string, 1: class-string|JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: Throwable}>
     * @throws \ReflectionException
     * @throws \JsonException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public static function failures(): iterable
    {
        yield from DecodeErrorCases::errors();
        yield from PhpDocReadingCases::errors();
        yield from PhpDocTagCases::errors();
        yield from PhpDocDocumentCases::errors();
        yield from NumericObjectKeyCases::mismatches();
        yield from AutoloadFailureCases::errors();
        yield from ParserSyntaxDecodeCases::failures();
        yield from EscapeDecodeCases::failures();
        yield from ConstantWildcardCases::failures();
        yield from DecodableGrammarCases::failures();
        yield from ImportedLiteralCases::failures();
        yield from MappedVisibilityCases::failures();
    }

    /**
     * validateType() returns the message of every failure caused by the declaration, and null for every other failure.
     *
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{class-string|JsonType<list<mixed>|object>, string|null}>
     * @throws \ReflectionException
     * @throws \JsonException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public static function validations(): iterable
    {
        $overrides = ValidationOverrides::messages();
        foreach (self::failures() as $label => [1 => $type, 2 => $message, 3 => $kind]) {
            $expected = $kind === DecodeErrorKind::UnsupportedDeclaration ? $message : null;
            yield 'decode failure: ' . $label => [
                $type,
                array_key_exists($label, $overrides) ? $overrides[$label]($type) : $expected,
            ];
        }
        foreach (DecodeSuccessCases::cases() as $label => [$case]) {
            $unknown = ValidationOverrides::DECODABLE[$label] ?? null;
            if ($unknown !== null) {
                $type = ($case->type)();
                yield 'decodable: ' . $label => [$type, ValidationOverrides::unknownClassMessage($type, $unknown)];
            }
        }
        foreach (TypeValidationCases::invalid() as $label => [$type, $message]) {
            yield 'declaration: ' . $label => [$type, $message];
        }
    }
}
