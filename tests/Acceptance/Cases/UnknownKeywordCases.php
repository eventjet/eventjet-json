<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Exception;
use InvalidArgumentException;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Cases for the unknown keywords that the JSON Schema classes collect with #[OtherMembers].
 *
 * @internal
 */
final class UnknownKeywordCases
{
    /**
     * @return iterable<string, array{object, string}>
     * @throws InvalidArgumentException
     */
    public static function documents(): iterable
    {
        yield 'draft 4 keeps unknown keywords such as $id' => [
            new Draft4(title: 'a', unknownKeywords: [
                '$id' => new JsonValue('https://example.com'),
                'x-internal' => new JsonValue(true),
            ]),
            '{"$id":"https://example.com","x-internal":true,"title":"a"}',
        ];
        yield 'draft 6 keeps id, which draft 4 used, next to $id' => [
            new Draft6(id: 'https://example.org', unknownKeywords: ['id' => new JsonValue('https://example.com')]),
            '{"id":"https://example.com","$id":"https://example.org"}',
        ];
        yield 'draft 2020-12 keeps keywords that it replaced' => [
            new Draft202012(unknownKeywords: [
                'definitions' => new JsonValue(new ArrayObject()),
                'dependencies' => new JsonValue(new ArrayObject()),
                '$recursiveRef' => new JsonValue('#'),
                'additionalItems' => new JsonValue(false),
            ]),
            '{"definitions":{},"dependencies":{},"$recursiveRef":"#","additionalItems":false}',
        ];
        yield 'unknown keywords of a nested schema' => [
            new Draft7(items: new Draft7(unknownKeywords: ['x-label' => new JsonValue('Name')])),
            '{"items":{"x-label":"Name"}}',
        ];
    }

    /**
     * Schemas built by hand whose unknown keywords have the name of a keyword of the draft. json_encode() rejects them
     * whether the schema has that keyword or not.
     *
     * @return iterable<string, array{string, class-string, callable(object): bool}>
     * @throws InvalidArgumentException
     */
    public static function encodings(): iterable
    {
        $value = new JsonValue('x');
        $schemas = [
            [new Draft4(title: 'a', unknownKeywords: ['title' => $value]), 'title'],
            [new Draft4(unknownKeywords: ['$ref' => $value]), '$ref'],
            [new Draft6(title: 'a', unknownKeywords: ['title' => $value]), 'title'],
            [new Draft6(unknownKeywords: ['$ref' => $value]), '$ref'],
            [new Draft7(title: 'a', unknownKeywords: ['title' => $value]), 'title'],
            [new Draft7(unknownKeywords: ['$ref' => $value]), '$ref'],
            [new Draft201909(title: 'a', unknownKeywords: ['title' => $value]), 'title'],
            [new Draft201909(unknownKeywords: ['$ref' => $value]), '$ref'],
            [new Draft202012(title: 'a', unknownKeywords: ['title' => $value]), 'title'],
            [new Draft202012(unknownKeywords: ['$ref' => $value]), '$ref'],
        ];
        foreach ($schemas as [$schema, $name]) {
            $expected = sprintf(
                'Cannot encode %s, because its unknown keywords include "%s", which is a keyword of the draft.',
                $schema::class,
                $name,
            );
            yield sprintf('%s rejects the unknown keyword "%s"', $schema::class, $name) => [
                '{}',
                $schema::class,
                static fn(object $_): bool => self::encoded($schema) === $expected,
            ];
        }
    }

    /** Returns the JSON for a schema, or the message of the exception that encoding it throws. */
    private static function encoded(object $schema): string
    {
        try {
            return json_encode($schema, JSON_THROW_ON_ERROR);
        } catch (Exception $error) {
            return $error->getMessage();
        }
    }
}
