<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/** @internal */
final class PublicProperties
{
    /** @var array<class-string, array<array-key, array{property: ReflectionProperty, node: Node}>> */
    private static array $properties = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return array<array-key, array{property: ReflectionProperty, node: Node}>|DecodeError
     * @phpstan-impure
     * @throws ReflectionException
     */
    public static function resolve(ReflectionClass $class): array|DecodeError
    {
        $name = $class->getName();
        $cached = self::$properties[$name] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $names = RootTypeValidator::fieldNames($class);
        if ($names instanceof DecodeError) {
            return $names;
        }
        $constructorFields = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructorFields[$parameter->getName()] = true;
        }

        $properties = [];
        foreach (RootTypeValidator::declarations($class) as $property) {
            $publicInstance =
                ($property->getModifiers() & (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC))
                === ReflectionProperty::IS_PUBLIC;
            if (!$publicInstance || ($constructorFields[$property->getName()] ?? false)) {
                continue;
            }
            $node = PublicPropertyTypeValidator::validate($name, $property);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $properties[$names[$property->getName()] ?? $property->getName()] = [
                'property' => $property,
                'node' => $node,
            ];
        }

        return self::$properties[$name] = $properties;
    }
}
