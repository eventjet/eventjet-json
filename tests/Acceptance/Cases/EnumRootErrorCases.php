<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class EnumRootErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'null' => ['null', 'null'],
            'string' => ['"1"', 'string'],
            'float' => ['1.0', 'float'],
            'bool' => ['true', 'bool'],
            'array' => ['[1]', 'array'],
            'object' => ['{"name":"Ready","value":1}', 'stdClass'],
        ] as $label => [$json, $type]) {
            yield 'int-backed enum root rejects ' . $label => [
                $json,
                IntBackedStatus::class,
                'Expected the JSON root to be an int backing value of backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus, got '
                    . $type
                    . '.',
                DecodeErrorKind::UnexpectedRootValue,
            ];
        }
        foreach ([
            'int' => ['1', 'int'],
            'object' => ['{"name":"Ready","value":"ready"}', 'stdClass'],
        ] as $label => [$json, $type]) {
            yield 'string-backed enum root rejects ' . $label => [
                $json,
                StringBackedStatus::class,
                'Expected the JSON root to be a string backing value of backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus, got '
                    . $type
                    . '.',
                DecodeErrorKind::UnexpectedRootValue,
            ];
        }
        yield 'int-backed enum root rejects an unknown case' => [
            '2',
            IntBackedStatus::class,
            'Backed enum Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus has no case with backing value 2.',
            DecodeErrorKind::InvalidValue,
        ];
        yield 'string-backed enum root rejects an unknown case' => [
            '"unknown"',
            StringBackedStatus::class,
            'Backed enum Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus has no case with backing value \'unknown\'.',
            DecodeErrorKind::InvalidValue,
        ];
    }
}
