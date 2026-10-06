<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

use function ltrim;
use function str_replace;

/** @internal */
final class CollectionUnionErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            'int|string' => ['true' => 'bool', 'null' => 'null', '[]' => 'array', '{}' => 'stdClass', '1.5' => 'float'],
            'true|int' => ['false' => 'bool'],
            'false|string' => ['true' => 'bool'],
            '\\' . StringBackedStatus::class . '|int' => ['false' => 'bool'],
            '\\' . IntBackedStatus::class . '|float' => ['"1"' => 'string'],
        ] as $type => $values) {
            foreach (CollectionUnionCases::shapes($type) as $shape => [$native, $declaration]) {
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                    foreach ($values as $value => $actual) {
                        $map = $shape === 'map' || $shape === 'ArrayObject';
                        $json = '{"value":' . ($map ? '{"key":' . $value . '}' : '[' . $value . ']') . '}';
                        $path = $map ? 'value[key]' : 'value[0]';
                        yield 'union mismatch ' . $declaration . $tag . $value => [
                            $json,
                            $class,
                            'Could not create '
                                . $class
                                . ' from the JSON object: Field '
                                . $path
                                . ' must be of type '
                                . self::resolved($type)
                                . ', '
                                . $actual
                                . ' given.',
                            3,
                        ];
                    }
                }
            }
        }

        yield from CollectionUnionDeclarationErrors::errors();
        yield from CollectionUnionEnumErrors::errors();
    }

    private static function resolved(string $type): string
    {
        return str_replace(['|\\', '<\\'], ['|', '<'], ltrim($type, characters: '\\'));
    }
}
