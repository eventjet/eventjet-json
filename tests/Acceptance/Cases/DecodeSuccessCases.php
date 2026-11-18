<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\JsonType;

use function array_key_exists;

/** @internal */
final class DecodeSuccessCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{DecodeSuccess}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public static function cases(): iterable
    {
        $labels = [];
        foreach (self::sources() as $source => $cases) {
            foreach ($cases as $label => $case) {
                $label = array_key_exists($label, $labels) ? $source . ': ' . $label : $label;
                $labels[$label] = true;
                yield $label => [$case];
            }
        }
    }

    /**
     * The type of every case that decodes without a first failure, and declarations that only validation reaches. See
     * {@see ValidationOverrides::DECODABLE} for the exceptions.
     *
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Closure(): (class-string|JsonType<list<mixed>|object>)}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public static function types(): iterable
    {
        foreach (self::cases() as $label => [$case]) {
            if ($case->prepare !== null || array_key_exists($label, ValidationOverrides::DECODABLE)) {
                continue;
            }
            yield $label => [$case->type];
        }
        yield from TypeValidationCases::valid();
    }

    /**
     * @return iterable<string, iterable<string, DecodeSuccess>>
     * @throws \ReflectionException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    private static function sources(): iterable
    {
        $roundTrip = self::roundTrip(...);
        $document = self::document(...);
        $lossy = self::lossy(...);
        yield 'object' => self::map(ObjectRoundTripCases::objects(), $roundTrip);
        yield 'PHPDoc reading' => self::map(PhpDocReadingCases::objects(), $roundTrip);
        yield 'root' => self::map(
            RootCollectionRoundTripCases::objects(),
            /** @param array{list<mixed>|object, callable(): JsonType<list<mixed>|object>} $row */
            static fn(array $row): DecodeSuccess => DecodeSuccess::rootRoundTrip($row[0], $row[1](...)),
        );
        yield 'document' => self::map(JsonFormattingRoundTripCases::objects(), $document);
        yield 'empty shape' => self::map(EmptyShapeRoundTripCases::objects(), $document);
        yield 'supported document' => self::map(
            SupportedDocumentRoundTripCases::objects(),
            /** @param array{string, class-string, callable(object): bool} $row */
            static fn(array $row): DecodeSuccess => DecodeSuccess::satisfying($row[0], $row[1], $row[2]),
        );
        yield 'constructor default' => self::map(ConstructorDefaultCases::objects(), $lossy);
        yield 'unknown field' => self::map(UnknownFieldCases::objects(), $lossy);
        yield 'parser syntax' => ParserSyntaxDecodeCases::successes();
        yield 'escape' => EscapeDecodeCases::successes();
        yield 'constant wildcard' => ConstantWildcardCases::successes();
        yield 'decodable grammar' => DecodableGrammarCases::successes();
        yield 'imported literal' => ImportedLiteralCases::successes();
        yield 'mapped visibility' => MappedVisibilityCases::successes();
        yield 'deferred dependency' => DeferredDependencyCases::successes();
    }

    /**
     * @template T of array
     * @param iterable<string, T> $cases
     * @param callable(T): DecodeSuccess $create
     * @return iterable<string, DecodeSuccess>
     */
    private static function map(iterable $cases, callable $create): iterable
    {
        foreach ($cases as $label => $arguments) {
            yield $label => $create($arguments);
        }
    }

    /** @param array{object} $row */
    private static function roundTrip(array $row): DecodeSuccess
    {
        return DecodeSuccess::roundTrip($row[0]);
    }

    /** @param array{object, string} $row */
    private static function document(array $row): DecodeSuccess
    {
        return DecodeSuccess::document($row[1], $row[0]);
    }

    /** @param array{string, object} $row */
    private static function lossy(array $row): DecodeSuccess
    {
        return DecodeSuccess::lossy($row[0], $row[1]);
    }
}
