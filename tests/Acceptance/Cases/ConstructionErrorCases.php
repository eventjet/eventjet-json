<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor;

/** @internal */
final class ConstructionErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'constructor throws an exception' => [
            '{"value":"rejected"}',
            ThrowingConstructor::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor from the JSON object: The constructor rejected the decoded value.',
            3,
            ThrowingConstructor::exception(),
        ];
        yield 'missing constructor arguments' => [
            '{}',
            Person::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Too few arguments to function Eventjet\Json\Test\Acceptance\Fixtures\Person::__construct(), 0 passed and at least 2 expected',
            3,
        ];
        yield from self::unknownItemClass(Person::class);
    }

    /**
     * @param class-string $class
     * @return iterable<string, array{string, JsonType<list<mixed>>, string, int}>
     */
    private static function unknownItemClass(string $class): iterable
    {
        /** @var class-string $missingClass */
        $missingClass = $class . 'Missing';

        yield 'unknown item class' => [
            '[]',
            JsonType::array($missingClass),
            'Could not create ' . $missingClass . ' from the JSON object: Class "' . $missingClass . '" does not exist',
            3,
        ];
    }
}
