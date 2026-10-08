<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionProperty;

/** @internal */
final class PublicProperties
{
    /** @var array<class-string, list<ReflectionProperty>> */
    private static array $properties = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @return list<ReflectionProperty>
     */
    public static function resolve(ReflectionClass $class): array
    {
        $name = $class->getName();
        $cached = self::$properties[$name] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $constructorFields = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructorFields[$parameter->getName()] = true;
        }

        $properties = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || ($constructorFields[$property->getName()] ?? false)) {
                continue;
            }
            $properties[] = $property;
        }

        return self::$properties[$name] = $properties;
    }
}
