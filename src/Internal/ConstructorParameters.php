<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionException;

/** @internal */
final class ConstructorParameters
{
    /** @var array<class-string, list<ConstructorParameter>> */
    private static array $parameters = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @throws ReflectionException
     * @return list<ConstructorParameter>
     */
    public static function resolve(ReflectionClass $class): array
    {
        $name = $class->getName();
        $cached = self::$parameters[$name] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $parameters = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $parameters[] = new ConstructorParameter($parameter, $class);
        }

        return self::$parameters[$name] = $parameters;
    }
}
