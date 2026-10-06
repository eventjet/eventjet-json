<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use JsonException;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class ConversionPathErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (ConversionPathInputs::leaves() as $name => [$native, $declaration, $wrong, $valid, $suffix]) {
            foreach (['param', 'var'] as $leafTag) {
                $leafClass = CollectionDeclarationFixture::create($native, $declaration, $leafTag);
                yield from NestedConversionPathCases::errors($name . $leafTag, $leafClass, $wrong, $suffix);

                foreach ([
                    'second list item' => [
                        'array',
                        'list<\\' . $leafClass . '>',
                        '[{"value":' . $valid . '},',
                        ']',
                        '[1]',
                    ],
                    'second map value' => [
                        'array',
                        'non-empty-array<string, \\' . $leafClass . '>',
                        '{"valid":{"value":' . $valid . '},"invalid":',
                        '}',
                        '[invalid]',
                    ],
                ] as $shape => [$parentNative, $parentDeclaration, $before, $after, $segment]) {
                    $class = CollectionDeclarationFixture::create($parentNative, $parentDeclaration, 'param');
                    yield 'conversion path ' . $name . $leafTag . $shape => [
                        '{"value":' . $before . '{"value":' . $wrong . '}' . $after . '}',
                        $class,
                        'Could not create '
                            . $leafClass
                            . ' from the JSON object: Field value'
                            . $segment
                            . '.value'
                            . $suffix,
                        3,
                    ];
                }
            }
        }

        foreach (['', 'a.b', 'a]b', 'a"b', "a\nb", "a\n", '雪'] as $key) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create('array', 'non-empty-array<string, int>', $tag);
                $quoted = json_encode($key, JSON_THROW_ON_ERROR);
                yield 'quoted map path ' . $quoted . $tag => [
                    '{"value":{' . $quoted . ':"wrong"}}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value['
                        . $quoted
                        . '] must be of type int, string given.',
                    3,
                ];
                $class = CollectionDeclarationFixture::create(
                    'array',
                    'non-empty-array<string, \\' . Person::class . '>',
                    $tag,
                );
                yield 'quoted nested map path ' . $quoted . $tag => [
                    '{"value":{' . $quoted . ':{"firstName":42,"lastName":"valid"}}}',
                    $class,
                    'Could not create '
                        . Person::class
                        . ' from the JSON object: Field value['
                        . $quoted
                        . '].firstName must be of type string, int given.',
                    3,
                ];
            }
        }
    }
}
