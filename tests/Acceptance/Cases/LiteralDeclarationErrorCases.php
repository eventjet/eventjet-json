<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\ExtendedLiteralFields;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

use const PHP_INT_MAX;

/** @internal */
final class LiteralDeclarationErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            ['int', (string) PHP_INT_MAX . '0'],
            ['float', '1e999'],
            ['string', '"\\u{110000}"'],
            ['int', '\\' . ExtendedLiteralFields::class . '::MISSING'],
            ['int', 'parent::NUMBER'],
            ['int', 'MissingLiteralClass::VALUE'],
        ] as [$native, $declaration]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                yield 'unsupported literal declaration ' . $declaration . ' in ' . $tag => [
                    '{}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field value uses unsupported literal declaration '
                        . $declaration
                        . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
                    3,
                ];
            }
        }
        $classes = '\\' . Coordinates::class . '|\\' . Person::class;
        $enum = '\\' . StringBackedStatus::class;
        $class = CollectionDeclarationFixture::create(
            $classes . '|' . $enum . '|string',
            $enum . "|'x'|string",
            'param',
        );
        yield 'class union errors precede literal enum union errors' => [
            '{}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value uses multiple class types: '
                . Coordinates::class
                . ', '
                . Person::class
                . '. JSON does not identify which class to instantiate.',
            3,
        ];
        $class = CollectionDeclarationFixture::create(
            $enum . '|string',
            '\\' . ExtendedLiteralFields::class . '::MISSING|string',
            'param',
        );
        yield 'enum union errors precede unresolved literal errors' => [
            '{}',
            $class,
            'Could not create '
                . $class
                . ' from the JSON object: Field value uses backed enum '
                . StringBackedStatus::class
                . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            3,
        ];
        foreach ([
            ['int|float', '1|1.0'],
            ['array|int|float', 'list<int>|1|1.0'],
            ['array', 'list<1|1.0>'],
            ['array', 'list<\\' . StringBackedStatus::class . '|\'ready\'>'],
            ['array', 'list<\\' . StringBackedStatus::class . '::Ready|\'ready\'>'],
            ['array', 'list<1|float>'],
            ['array', 'list<1.0|int>'],
            ['array', 'list<\\' . StringBackedStatus::class . '::Ready|string>'],
        ] as [$native, $declaration]) {
            $class = CollectionDeclarationFixture::create($native, $declaration, 'param');
            yield 'ambiguous literal declaration ' . $declaration => [
                '{}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value has overlapping literal union members. JSON cannot identify which PHP type to restore.',
                3,
            ];
        }
    }
}
