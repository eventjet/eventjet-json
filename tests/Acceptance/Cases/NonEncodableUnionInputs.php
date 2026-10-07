<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class NonEncodableUnionInputs
{
    /** @return iterable<string, list<bool|float|int|string|object|null>> */
    public static function nativeDomains(): iterable
    {
        $enum = '\\' . NonBackedStatus::class;
        foreach (self::domains() as $supported => $values) {
            yield $enum . '|' . $supported => $values;
            yield $supported . '|' . $enum => $values;
            yield $enum . '|\\' . NonBackedOutcome::class . '|' . $supported => $values;
        }
    }

    /** @return iterable<string, list<bool|float|int|string|object|null>> */
    public static function domains(): iterable
    {
        yield 'null' => [null];
        yield 'string|int|float|bool|null' => ['', '42', "雪\n", PHP_INT_MIN, PHP_INT_MAX, 1.25, true, false, null];
        yield 'float' => [0.0, 3.0, -1.25];
        yield 'true' => [true];
        yield 'false' => [false];
        yield '\\'
            . Coordinates::class
            . '|\\'
            . StringBackedStatus::class
            . '|\\'
            . StringBackedOutcome::class
            . '|\\'
            . IntBackedStatus::class
            . '|bool|null' => [
            new Coordinates(1.0, 2.0),
            StringBackedStatus::Ready,
            StringBackedOutcome::Complete,
            IntBackedStatus::Ready,
            false,
            null,
        ];
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function object(string $native, string $declaration, string $tag, mixed $value): object
    {
        $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
        $reflection = new ReflectionClass($class);
        if ($tag === 'param') {
            return $reflection->newInstance($value);
        }
        $object = $reflection->newInstance();
        $reflection->getProperty('value')->setValue($object, $value);
        return $object;
    }
}
