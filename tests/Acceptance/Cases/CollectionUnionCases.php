<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\FloatUnionList;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function array_reverse;
use function explode;
use function implode;
use function strtolower;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/** @internal */
final class CollectionUnionCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        yield 'float union preserves actual item types' => [new FloatUnionList([
            0.0,
            3.0,
            -1.25,
            null,
        ])];
        foreach (self::domains() as $union => $values) {
            foreach ([$union, implode('|', array_reverse(explode('|', $union)))] as $order => $type) {
                foreach (self::shapes($type) as $shape => [$native, $declaration]) {
                    foreach (['param', 'var'] as $tag) {
                        $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                        $reflection = new ReflectionClass($class);
                        foreach (CollectionUnionInputs::collections($shape, $values) as $index => $collection) {
                            $object = $tag === 'param'
                                ? $reflection->newInstance($collection)
                                : $reflection->newInstance();
                            if ($tag === 'var') {
                                $reflection->getProperty('value')->setValue($object, $collection);
                            }
                            yield 'collection union ' . $type . $order . $shape . $tag . $index => [$object];
                        }
                    }
                }
            }
        }
    }

    /** @return iterable<string, list<bool|float|int|string|object|null>> */
    private static function domains(): iterable
    {
        yield 'string|int|float|bool|null' => ['', '42', "雪\n", PHP_INT_MIN, 0, PHP_INT_MAX, 1.25, true, false, null];
        yield 'float|null' => [0.0, 3.0, -2.5, null];
        yield 'true|int' => [true, 0, 42];
        yield 'false|string' => [false, '', 'false'];
        yield 'non-empty-string|int<0, max>' => ['answer', 42];
        yield 'int|positive-int' => [0, 42];
        yield 'int|non-zero-int' => [PHP_INT_MIN, 0, PHP_INT_MAX];
        yield 'non-zero-int|null' => [PHP_INT_MIN, -1, 0, 1, PHP_INT_MAX, null];
        yield '\\' . Coordinates::class . '|string|int|null' => [new Coordinates(1.25, 2.5), 'text', 42, null];
        yield '\\'
            . StringBackedStatus::class
            . '|\\'
            . StringBackedOutcome::class
            . '|\\'
            . IntBackedStatus::class
            . '|\\'
            . Coordinates::class
            . '|bool|null' => [
            StringBackedStatus::Ready,
            StringBackedStatus::Pending,
            StringBackedOutcome::Complete,
            StringBackedOutcome::Failed,
            IntBackedStatus::Ready,
            IntBackedStatus::Pending,
            new Coordinates(3.0, 4.0),
            true,
            false,
            null,
        ];
        yield '\\' . IntBackedStatus::class . '|float' => [IntBackedStatus::Ready, IntBackedStatus::Pending, 1.5];
        yield '\\' . StringBackedStatus::class . '|\\' . strtolower(StringBackedStatus::class) . '|int' => [
            StringBackedStatus::Ready,
            42,
        ];
    }

    /** @return iterable<string, array{string, string}> */
    public static function shapes(string $type): iterable
    {
        yield 'list' => ['array', 'list<' . $type . '>'];
        yield 'non-empty-list' => ['array', 'non-empty-list<' . $type . '>'];
        yield 'tuple' => ['array', 'array{' . $type . '}'];
        yield 'optional tuple' => ['array', 'array{0?: ' . $type . '}'];
        yield 'map' => ['array', 'non-empty-array<string, ' . $type . '>'];
        yield 'non-empty-map' => ['array', 'non-empty-map<string, ' . $type . '>'];
        yield 'ArrayObject' => ['\\ArrayObject', 'ArrayObject<string, ' . $type . '>'];
    }
}
