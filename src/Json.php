<?php

declare(strict_types=1);

namespace Eventjet\Json;

use BackedEnum;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;
use Throwable;
use UnitEnum;

use function assert;
use function class_exists;
use function is_a;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function property_exists;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class Json
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function decode(string $json, string $class): object
    {
        $data = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
        if (!$data instanceof stdClass) {
            throw JsonError::decodeFailed('Expected JSON object at the root.');
        }
        return self::createObject($data, $class);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function createObject(stdClass $data, string $class): object
    {
        $arguments = self::buildConstructorArguments($data, $class);
        try {
            /** @psalm-suppress MixedMethodCall */
            return new $class(...$arguments);
        } catch (Throwable $e) {
            $result = preg_match(
                '/Argument #\d+ \(\$(?<name>.+)\) must be of type (?<expected>.+), (?<got>.+) given, called in/',
                $e->getMessage(),
                $matches,
            );
            if ($result !== 1) {
                throw $e;
            }
            throw JsonError::decodeFailed(sprintf(
                'Field "%s" expected to be of type %s, got %s.',
                $matches['name'],
                $matches['expected'],
                $matches['got'],
            ));
        }
    }

    /**
     * @param class-string $class
     * @return array<string, mixed>
     */
    private static function buildConstructorArguments(stdClass $data, string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            throw JsonError::decodeFailed(sprintf('Class %s does not have a constructor.', $class));
        }
        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (!property_exists($data, $parameter->name)) {
                if ($parameter->isOptional()) {
                    continue;
                }
                throw JsonError::decodeFailed(
                    sprintf('Missing required property "%s" in JSON data for class %s.', $parameter->getName(), $class),
                );
            }
            /** @psalm-suppress MixedAssignment */
            $value = self::buildConstructorArgument($data, $parameter);
            /** @psalm-suppress MixedAssignment */
            $args[$parameter->name] = $value;
        }
        return $args;
    }

    private static function buildConstructorArgument(stdClass $data, ReflectionParameter $parameter): mixed
    {
        /** @var mixed $value */
        $value = $data->{$parameter->name};
        $parameterType = $parameter->getType();
        if ($parameterType instanceof ReflectionNamedType) {
            $typeName = $parameterType->getName();
            $class = $parameter->getDeclaringClass();
            assert($class !== null);
            $className = $class->getName();
            if ($parameter->isOptional() && $parameter->getDefaultValue() === null && $value === null) {
                return null;
            }
            if ($typeName === 'object') {
                throw JsonError::decodeFailed(sprintf(
                    '"object" is not allowed as a type for property "%s" in class %s, use a specific class name instead.',
                    $parameter->name,
                    $className,
                ));
            }
            if ($value instanceof stdClass) {
                if ($typeName === 'mixed') {
                    throw JsonError::decodeFailed(sprintf(
                        'To populate a property with an object, it must have a specific class type instead of mixed (property "%s" in class %s).',
                        $parameter->name,
                        $className,
                    ));
                }
                if (class_exists($typeName)) {
                    return self::createObject($value, $typeName);
                }
                throw JsonError::decodeFailed(sprintf(
                    'Can\'t populate property "%s" of type %s with JSON object.',
                    $parameter->name,
                    $typeName,
                ));
            }
            if (class_exists($typeName)) {
                if (is_a($typeName, UnitEnum::class, true)) {
                    if (!is_a($typeName, BackedEnum::class, true)) {
                        throw JsonError::decodeFailed(sprintf(
                            'All enums must be backed, but %s is not a backed enum.',
                            $typeName,
                        ));
                    }
                    if (!is_int($value) && !is_string($value)) {
                        throw JsonError::decodeFailed(sprintf(
                            '%s is not a valid a value of any case of enum %s.',
                            json_encode($value, JSON_THROW_ON_ERROR),
                            $typeName,
                        ));
                    }
                    $backingType = (new ReflectionEnum($typeName))->getBackingType();
                    assert($backingType instanceof ReflectionNamedType);
                    $backingTypeName = $backingType->getName();
                    if (
                        ($backingTypeName === 'int' && !is_int($value)) ||
                        ($backingTypeName === 'string' && !is_string($value))
                    ) {
                        throw JsonError::decodeFailed(sprintf(
                            '%s is not a valid a value of any case of enum %s.',
                            json_encode($value, JSON_THROW_ON_ERROR),
                            $typeName,
                        ));
                    }
                    $enum = $typeName::tryFrom($value);
                    if ($enum === null) {
                        throw JsonError::decodeFailed(sprintf(
                            '%s is not a valid a value of any case of enum %s.',
                            json_encode($value, JSON_THROW_ON_ERROR),
                            $typeName,
                        ));
                    }
                    return $enum;
                }
            }
        }
        return $value;
    }
}
