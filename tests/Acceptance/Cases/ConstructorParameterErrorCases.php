<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\MissingConstructorParameterProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\ProtectedConstructorParameterProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty;
use JsonException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class ConstructorParameterErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     */
    public static function errors(): iterable
    {
        foreach ([
            'missing property' => new MissingConstructorParameterProperty('value'),
            'protected property' => new ProtectedConstructorParameterProperty('value'),
            'static property' => new StaticConstructorParameterProperty('value'),
        ] as $name => $target) {
            $class = $target::class;

            yield 'constructor parameter rejects ' . $name => [
                json_encode($target, JSON_THROW_ON_ERROR),
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Constructor parameter value has no same-named declared public instance property. The target class does not expose a stable JSON member from which the argument can be recovered.',
                3,
            ];
        }
    }
}
