<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionClass;
use ReflectionException;

use function array_flip;

/** @internal */
final class ConstructorDecoder
{
    /** @var array<class-string, ConstructorPlan|MappedConstructorPlan> */
    private static array $plans = [];

    /**
     * @param class-string $class
     * @phpstan-impure
     */
    public static function cachedPlan(string $class): ConstructorPlan|null
    {
        $plan = self::$plans[$class] ?? null;
        return $plan instanceof ConstructorPlan ? $plan : null;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @phpstan-impure
     * @throws ReflectionException
     * @throws JsonException
     */
    public static function convert(ReflectionClass $class, array $values, string $path): array|DecodeError
    {
        $className = $class->getName();
        $plan = self::$plans[$className] ?? null;
        if ($plan !== null) {
            return $plan->decode($values, $path);
        }

        $names = RootTypeValidator::fieldNames($class);
        if ($names instanceof DecodeError) {
            return $names;
        }
        $fields = [];
        $cacheable = true;
        $converters = [];
        $constructor = $class->getConstructor();
        $docComment = $constructor?->getDocComment() ?? false;
        $hasLiteralPhpDoc = $docComment === false ? false : null;

        foreach ($constructor?->getParameters() ?? [] as $reflection) {
            $parameter = new ConstructorParameter($reflection, $class, $docComment, $hasLiteralPhpDoc);
            $name = $names[$parameter->name] ?? $parameter->name;
            $resolved = $parameter->resolveType($className);
            $cacheable = $cacheable && $resolved !== null;

            if ($resolved instanceof DecodeError) {
                return $resolved;
            }

            $converters[$name] = $parameter->converter($resolved);
            $error = ConstructorValueValidator::addParameter($fields, $parameter, $values, $name, $path);
            if ($error !== null) {
                return $error;
            }
        }

        $plan = new ConstructorPlan($className, $fields, $converters);
        if ($names !== []) {
            $plan = new MappedConstructorPlan($plan, array_flip($names));
        }
        if ($cacheable) {
            self::$plans[$className] = $plan;
        }
        return $plan->convert($values, $path);
    }
}
