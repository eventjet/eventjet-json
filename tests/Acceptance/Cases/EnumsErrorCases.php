<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class EnumsErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'nested enum list rejects the wrong backing type' => [
            '[["1"]]',
            JsonType::array(JsonType::array(IntBackedStatus::class)),
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus from the JSON object: Field [0][0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus, which expects a int backing value; string given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'enum list declared in PHPDoc rejects null' => [
            '{"value":[null]}',
            CollectionDeclarationFixture::create('array', PhpType::list(IntBackedStatus::class), 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('array', PhpType::list(IntBackedStatus::class), 'param')
                . ' from the JSON object: Field value[0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus, which expects a int backing value; null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        $nullableItems = CollectionDeclarationFixture::create(
            'array',
            PhpType::list(PhpType::union(StringBackedStatus::class, PhpType::Null)),
            'param',
        );
        yield 'nullable enum list item rejects the wrong backing type' => [
            '{"value":[1]}',
            $nullableItems,
            'Could not create '
                . $nullableItems
                . ' from the JSON object: Field value[0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which expects a string backing value; int given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        $repeatedEnum = CollectionDeclarationFixture::create(
            'array',
            PhpType::list(PhpType::union(StringBackedStatus::class, StringBackedStatus::class)),
            'param',
        );
        yield 'repeated enum union member decodes like the enum' => [
            '{"value":[1]}',
            $repeatedEnum,
            'Could not create '
                . $repeatedEnum
                . ' from the JSON object: Field value[0] uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which expects a string backing value; int given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'nullable non-backed enum rejects a non-null value' => [
            '{"value":1}',
            CollectionDeclarationFixture::create('?\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus', '', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create(
                    '?\Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus',
                    '',
                    'var',
                )
                . ' from the JSON object: Field value must be of type ?Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, int given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'string-backed enum rejects null' => [
            '{"stringStatus":null,"nullableStatus":null,"intStatus":1}',
            BackedEnumFields::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields from the JSON object: Field stringStatus uses backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, which expects a string backing value; null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
    }
}
