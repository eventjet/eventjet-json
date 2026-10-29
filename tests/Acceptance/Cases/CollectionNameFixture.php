<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function class_exists;

/** @internal */
final class CollectionNameFixture
{
    /**
     * @param array{0: string, 1: string, 2: object|null, 3?: string} $specification
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public static function create(array $specification, string $tag, string $shape, string $scope): object
    {
        [$className, $itemClass] = CollectionNameDeclaration::source($specification, $tag, $shape, $scope);
        $item = $specification[2];
        $value = $item;
        if ($value === null) {
            if (!class_exists($itemClass)) {
                throw new RuntimeException('The item fixture did not declare its class.');
            }
            $value = new ReflectionClass($itemClass)->newInstance();
        }
        $collection = match ($shape) {
            'non-empty-array' => ['primary' => $value],
            ArrayObject::class => new ArrayObject(['primary' => $value]),
            default => [$value],
        };
        $reflection = new ReflectionClass($className);
        $original = $tag === 'param' ? $reflection->newInstance($collection) : $reflection->newInstance();
        if ($tag === 'var') {
            $reflection->getProperty('value')->setValue($original, $collection);
        }
        return $original;
    }
}
