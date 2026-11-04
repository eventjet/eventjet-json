<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\JsonSchema;

/**
 * Schema documents with the draft class that JsonSchema::decode() picks for them, and documents it cannot decode.
 *
 * @internal
 */
final class JsonSchemaCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, (Closure(string): mixed)|null, class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>|bool}>
     */
    public static function decodedDrafts(): iterable
    {
        yield 'no $schema' => ['{"type":"string"}', null, Draft7::class];
        yield 'Draft 4' => ['{"$schema":"http://json-schema.org/draft-04/schema#"}', null, Draft4::class];
        yield 'Draft 6 without the empty fragment' => [
            '{"$schema":"http://json-schema.org/draft-06/schema"}',
            null,
            Draft6::class,
        ];
        yield 'Draft 2019-09' => [
            '{"$schema":"https://json-schema.org/draft/2019-09/schema"}',
            null,
            Draft201909::class,
        ];
        yield 'Draft 2020-12 with an empty fragment' => [
            '{"$schema":"https://json-schema.org/draft/2020-12/schema#"}',
            null,
            Draft202012::class,
        ];
        yield 'custom meta-schema' => [
            '{"$schema":"https://example.com/meta"}',
            static fn(): Draft201909 => new Draft201909(),
            Draft201909::class,
        ];
        yield 'chain of custom meta-schemas' => [
            '{"$schema":"https://example.com/a"}',
            self::metaSchemaLoader([
                'https://example.com/a' => '{"$schema":"https://example.com/b"}',
                'https://example.com/b' => '{"$schema":"http://json-schema.org/draft-06/schema#"}',
            ]),
            Draft6::class,
        ];
        yield 'true' => ['true', null, true];
        yield 'false' => ['false', null, false];
    }

    /**
     * A loader that decodes the given meta-schemas with JsonSchema::decode(), following their own `$schema`.
     *
     * @param array<string, string> $documents
     * @return Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null)
     */
    private static function metaSchemaLoader(array $documents): Closure
    {
        $loader = static function (string $uri) use (
            &$loader,
            $documents,
        ): Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null {
            $json = $documents[$uri] ?? null;
            /** @var Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null) $loader */
            return $json === null ? null : JsonSchema::decode($json, Draft202012::class, loader: $loader);
        };
        return $loader;
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, (Closure(string): mixed)|null, string, DecodeErrorKind}>
     */
    public static function decodeErrors(): iterable
    {
        yield 'invalid JSON' => ['{', null, 'Invalid JSON: Syntax error', DecodeErrorKind::InvalidJson];
        yield 'unknown $schema without a loader' => [
            '{"$schema":"https://example.com/meta"}',
            null,
            'The $schema https://example.com/meta is not an official meta-schema, and there is no loader to load it.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'loader finds nothing' => [
            '{"$schema":"https://example.com/meta"}',
            static fn(): null => null,
            'The loader found no meta-schema for the $schema https://example.com/meta.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'loop of meta-schemas' => [
            '{"$schema":"https://example.com/a"}',
            self::metaSchemaLoader([
                'https://example.com/a' => '{"$schema":"https://example.com/b"}',
                'https://example.com/b' => '{"$schema":"https://example.com/a"}',
            ]),
            'The meta-schema https://example.com/a is its own meta-schema through a loop of $schema.',
            DecodeErrorKind::InvalidValue,
        ];
    }
}
