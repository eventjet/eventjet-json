<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\ProtectedConstructorTarget;

/** @internal */
final class InaccessibleConstructorErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        $privateConstructorTarget = PrivateConstructorTarget::create();

        yield 'private constructor' => [
            '{}',
            $privateConstructorTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget from the JSON object: Target class has a private constructor, which cannot be called to create the object.',
            3,
        ];

        $protectedConstructorTarget = ProtectedConstructorTarget::create();

        yield 'protected constructor' => [
            '{}',
            $protectedConstructorTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ProtectedConstructorTarget from the JSON object: Target class has a protected constructor, which cannot be called to create the object.',
            3,
        ];
    }
}
