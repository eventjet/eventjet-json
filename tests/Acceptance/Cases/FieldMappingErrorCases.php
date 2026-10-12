<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedDocument;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedProperties;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedReference;
use Eventjet\Json\Test\Acceptance\Fixtures\MappedSpecialNames;
use RuntimeException;

use function sha1;
use function sprintf;

/** @internal */
final class FieldMappingErrorCases
{
    /**
     * @return iterable<string, array{string, class-string|JsonType<list<mixed>>, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            'missing interface' => [
                'public function __construct(#[Field(\'$ref\')] public string $ref = "") {}',
                '',
                '#[Field] requires the target class to implement JsonSerializable.',
            ],
            'same-name attribute requires interface' => [
                'public function __construct(#[Field("ref")] public string $ref = "") {}',
                '',
                '#[Field] requires the target class to implement JsonSerializable.',
            ],
            'invalid attribute argument' => [
                '#[Field(missing: "wire")] public string $ref = "";',
                ' implements JsonSerializable',
                'Unknown named parameter $missing',
            ],
            'null-prefixed name' => [
                '#[Field("\\0hidden")] public string $ref = "";',
                ' implements JsonSerializable',
                'JSON field names must not start with a null byte because PHP treats them as non-public object properties.',
            ],
            'private field' => [
                '#[Field(\'$ref\')] private string $ref = "";',
                ' implements JsonSerializable',
                '#[Field] requires a public instance property; ref is not one.',
            ],
            'static field' => [
                '#[Field(\'$ref\')] public static string $ref = "";',
                ' implements JsonSerializable',
                '#[Field] requires a public instance property; ref is not one.',
            ],
            'duplicate annotation' => [
                '#[Field("a"), Field("b")] public string $ref = "";',
                ' implements JsonSerializable',
                'Property ref must not repeat #[Field].',
            ],
            'collision with ordinary field' => [
                '#[Field("name")] public string $ref = ""; public string $name = "";',
                ' implements JsonSerializable',
                'Properties ref and name use the same JSON field name "name".',
            ],
            'collision with mapped field' => [
                '#[Field("wire")] public string $ref = ""; #[Field("wire")] public string $name = "";',
                ' implements JsonSerializable',
                'Properties ref and name use the same JSON field name "wire".',
            ],
        ] as $label => [$body, $interface, $reason]) {
            $shortName = 'FieldMapping_' . sha1($body);
            $class = CollectionNameSource::load(
                $shortName,
                'use Eventjet\\Json\\Field; final class '
                . $shortName
                . $interface
                . ' {'
                . $body
                . ' public function jsonSerialize(): object { return (object) []; }}',
            );
            $message = sprintf('Could not create %s from the JSON object: %s', $class, $reason);
            yield $label => ['{}', $class, $message, 3];
            yield $label . ' empty root collection' => ['[]', JsonType::array($class), $message, 3];
            foreach (['param', 'var'] as $tag) {
                yield $label . ' omitted nested ' . $tag => [
                    '{}',
                    CollectionDeclarationFixture::create('?' . $class, '', $tag),
                    $message,
                    3,
                ];
                yield $label . ' empty list ' . $tag => [
                    '{"value":[]}',
                    CollectionDeclarationFixture::create('array', 'list<' . $class . '>', $tag),
                    $message,
                    3,
                ];
            }
        }
        yield 'mapped constructor type mismatch' => [
            '{"$ref":42}',
            MappedReference::class,
            'Could not create '
                . MappedReference::class
                . ' from the JSON object: Field $ref must be of type string, int given.',
            3,
        ];
        yield 'mapped nested path' => [
            '{"$id":"id","links":[{"$ref":42}]}',
            MappedDocument::class,
            'Could not create '
                . MappedReference::class
                . ' from the JSON object: Field links[0].$ref must be of type string, int given.',
            3,
        ];
        yield 'mapped public property mismatch' => [
            '{"$anchor":false}',
            MappedProperties::class,
            'Could not create '
                . MappedProperties::class
                . ' from the JSON object: Field $anchor must be of type string, bool given.',
            3,
        ];
        yield 'mapped public collection mismatch' => [
            '{"links":[{"$ref":42}]}',
            MappedProperties::class,
            'Could not create '
                . MappedReference::class
                . ' from the JSON object: Field links[0].$ref must be of type string, int given.',
            3,
        ];
        yield 'numeric mapped constructor mismatch' => [
            '{"0":42}',
            MappedSpecialNames::class,
            'Could not create '
                . MappedSpecialNames::class
                . ' from the JSON object: Field 0 must be of type string, int given.',
            3,
        ];
    }
}
