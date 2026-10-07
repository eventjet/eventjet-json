<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use RuntimeException;

/** @internal */
final class NonEncodableUnionBoundaryErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        $enum = '\\' . NonBackedStatus::class;
        foreach (['param', 'var'] as $tag) {
            foreach (['resource|open-resource', 'closed-resource|resource'] as $union) {
                $class = CollectionDeclarationFixture::create('array', 'list<' . $union . '>', $tag);
                $first = $union === 'resource|open-resource' ? 'resource' : 'closed-resource';
                yield 'resource-only union ' . $union . $tag => [
                    '{}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value uses non-JSON-encodable type '
                        . $first
                        . ', which has no JSON representation.',
                    3,
                ];
            }
            $class = CollectionDeclarationFixture::create('?' . $enum, '', $tag);
            foreach (['"ready"' => 'string', '1' => 'int', '{}' => 'stdClass', '[]' => 'array'] as $json => $actual) {
                yield 'nullable non-backed enum rejects non-null ' . $tag . $json => [
                    '{"value":' . $json . '}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value must be of type ?'
                        . NonBackedStatus::class
                        . ', '
                        . $actual
                        . ' given.',
                    3,
                ];
            }
            $class = CollectionDeclarationFixture::create(
                'array',
                'array{ArrayObject<string, list<resource|null>>}',
                $tag,
            );
            yield 'nested non-encodable union mismatch path ' . $tag => [
                '{"value":[{"a.b":[42]}]}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value[0]["a.b"][0] must be of type resource|null, int given.',
                3,
            ];
        }
    }
}
