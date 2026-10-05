<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\VariadicConstructorTarget;
use JsonException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class VariadicConstructorErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        $variadicTarget = new VariadicConstructorTarget('one', 'two');

        foreach ([
            '{}',
            '{"values":[]}',
            json_encode($variadicTarget, JSON_THROW_ON_ERROR),
            '{"values":"one"}',
        ] as $json) {
            yield 'variadic constructor rejects ' . $json => [
                $json,
                VariadicConstructorTarget::class,
                'Could not create '
                    . VariadicConstructorTarget::class
                    . ' from the JSON object: Constructor parameter values is variadic. JSON members bind to individual named arguments, not variadic argument lists.',
                3,
            ];
        }
    }
}
