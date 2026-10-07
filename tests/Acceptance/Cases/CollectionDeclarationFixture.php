<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

use function class_exists;
use function file_put_contents;
use function is_file;
use function sha1;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @internal */
final class CollectionDeclarationFixture
{
    /** @return iterable<string, array{0: string, 1: string, 2?: string|null, 3?: list<string>}> */
    public static function declarations(): iterable
    {
        yield from self::invalidUnionDeclarations();
        yield 'missing' => ['array', ''];
        yield 'unrelated tag' => ['array', '/** @return list<int> */'];
        yield 'wrong map field' => ['array', '/** @param non-empty-array<string, int> $values */'];
        yield 'wrong field' => ['array', '/** @param list<int> $values */'];

        foreach ([
            'non-empty-map<string, int',
            'non-empty-map<string>',
            'non-empty-map<string, int, bool>',
            'non-empty-map<string, UnknownCollectionItem>',
            'list<non-zero-int<string>>',
            'non-empty-list<>',
            'non-empty-list<int',
            'non-empty-list<UnknownCollectionItem>',
            'list<list<UnknownCollectionItem>>',
            'list<non-empty-array<int, int>>',
            'list<non-empty-array<string, int, string>>',
            'list<ArrayObject<int, int>>',
            'list<array<string, int>>',
            'list<array{int}>',
            'list<list<int>|null<string>>',
            'list<int|null<string>>',
            'list<>',
            'list<int',
            'list<int>>',
            'list<int, string>',
            'list<UnknownCollectionItem>',
            'list<\\UnknownCollectionItem>',
            'list<mixed>',
            'list<int|UnknownUnionItem>',
            'list<int|true<string>>',
            'list<int|Foo&Bar>',
            'array{0: int, 1?: UnknownTupleItem}',
            'array{1: int}',
            'array{0: int, 2?: string}',
            'array{0: int, 0: string}',
            'array{1: int, 0: string}',
            'array{00: int}',
            'array{-1: int}',
            'array{+0: int}',
            'array{0?: int, 1: string}',
            'array{0?: int, string}',
            'array{0??: int}',
            'array{0: int?, 1: string}',
            'array{0: int, 1?: list<int>}',
            'array{0: int, 1?: string, ...}',
            'array{0: int, 1?: string}|string',
            'array{0: int, 1?: string}[]',
            'array{int, UnknownTupleItem}',
            'array{int, string',
            'array{int,, string}',
            'array{int, string}|string',
            'array{int, string} | string',
            'array{int, string}[]',
            'array{int, string, ...}',
            'array{int, string?}',
            'array{name: string}',
            'array{list<int>}',
            'array{list<int>|string}',
            'int[]',
            'list<int>|string',
            'list<int> | string',
            'non-empty-array<string, UnknownCollectionItem>',
            'non-empty-array<string, mixed>',
            'non-empty-array<string, int',
        ] as $type) {
            yield $type => ['array', $type];
        }

        foreach (['UnknownCollectionItem', 'mixed', 'list<UnknownCollectionItem>'] as $type) {
            yield 'ArrayObject ' . $type => ['\\ArrayObject', 'ArrayObject<string, ' . $type . '>'];
        }

        foreach (['', 'ArrayObject', 'ArrayObject<string>', 'ArrayObject<string, int', 'list<int>'] as $type) {
            yield 'invalid ArrayObject ' . $type => [
                '\\ArrayObject',
                $type,
                'Field value uses unsupported map declaration ArrayObject. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            ];
        }

        yield 'native list union' => ['array|string', 'list<int>'];
        yield 'native map union' => ['array|string', 'non-empty-array<string, int>'];
    }

    /** @return iterable<string, array{string, string, null, list<string>}> */
    public static function invalidUnionDeclarations(): iterable
    {
        yield 'mismatched collection and ambiguous enum union' => [
            'array|\\' . StringBackedStatus::class . '|string',
            'list<int>|\\' . StringBackedStatus::class . '|string|bool',
            null,
            ['{}', '{"value":[]}'],
        ];
        yield 'malformed collection union true' => [
            'array|true',
            'list<int>|true<string>',
            null,
            ['{}', '{"value":[]}'],
        ];
        yield 'malformed collection union null' => ['?array', 'list<int>|null<string>', null, ['{}', '{"value":[]}']];
        $documents = ['{}', '{"value":[]}'];
        yield 'mismatched collection union scalar' => ['array|string', 'list<int>|int', null, $documents];
        yield 'extra collection union null' => ['array|string', 'list<int>|string|null', null, $documents];
        yield 'missing collection union member' => ['array|string', 'int|string', null, $documents];
        yield 'unknown collection union member' => ['array|string', 'list<int>|UnknownItem', null, $documents];
        yield 'bare lowercase ArrayObject union member' => [
            'array|\\ArrayObject',
            'list<int>|arrayobject',
            null,
            $documents,
        ];
        yield 'bare ArrayObject union member' => ['array|\\ArrayObject', 'list<int>|ArrayObject', null, $documents];
        yield 'malformed tuple union' => ['array|string', 'array{1: int}|string', null, $documents];
        yield 'missing nullable collection declaration' => ['?array', 'list<int>', null, $documents];
    }

    /**
     * @return class-string
     * @throws RuntimeException
     */
    public static function create(string $nativeType, string $declaration, string $tag): string
    {
        $doc = match (true) {
            $declaration === '' => '',
            str_starts_with($declaration, '/**') => $declaration,
            default => '/** @' . $tag . ' ' . $declaration . ($tag === 'param' ? ' $value' : '') . ' */',
        };
        $field = $tag === 'param'
            ? $doc . "\n" . 'public function __construct(public ' . $nativeType . ' $value) {}'
            : $doc . "\n" . 'public ' . $nativeType . ' $value;';
        $class = 'InvalidCollection_' . sha1($field);

        if (class_exists($class)) {
            return $class;
        }

        // Invalid PHPDoc is input data for the decoder, not a declaration in the test implementation.
        $path = tempnam(sys_get_temp_dir(), prefix: 'json-collection-');

        if ($path === false) {
            throw new RuntimeException('Could not create a collection fixture file.');
        }

        try {
            $written = file_put_contents($path, '<?php final class ' . $class . ' {' . $field . '}');

            if ($written === false) {
                throw new RuntimeException('Could not write the collection fixture.');
            }

            $exists = is_file($path);

            if (!$exists) {
                throw new RuntimeException('The collection fixture file is missing.');
            }

            require $path;
        } finally {
            unlink($path);
        }

        if (!class_exists($class)) {
            throw new RuntimeException('The collection fixture did not declare its class.');
        }

        return $class;
    }
}
