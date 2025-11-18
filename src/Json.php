<?php

declare(strict_types=1);

namespace Eventjet\Json;

use BackedEnum;
use ReflectionClass;
use ReflectionEnum;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use stdClass;
use Throwable;
use UnitEnum;

use function array_filter;
use function array_is_list;
use function array_map;
use function assert;
use function class_exists;
use function count;
use function gettype;
use function implode;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
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
            ), $e);
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
        $class = $parameter->getDeclaringClass();
        assert($class !== null);
        if ($parameterType instanceof ReflectionNamedType) {
            $typeName = $parameterType->getName();
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
            if ($typeName === 'array') {
                $itemType = self::getArrayItemType($parameter);
                if (!is_array($value)) {
                    throw JsonError::decodeFailed(sprintf(
                        'Expected array for property "%s", got %s.',
                        $parameter->name,
                        self::jsonTypeOfPhpValue($value),
                    ));
                }
                assert(array_is_list($value));
                $array = [];
                /** @var mixed $itemValue */
                foreach ($value as $index => $itemValue) {
                    /** @psalm-suppress MixedAssignment */
                    $array[] = self::arrayItem($itemValue, $itemType, $index);
                }
                return $array;
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
                    return self::createEnumCase($value, $typeName);
                }
            }
        }
        if ($parameterType instanceof ReflectionUnionType) {
            if ($value instanceof stdClass) {
                $classTypes = self::classTypes($parameterType->getTypes());
                $numberOfClassTypes = count($classTypes);
                if ($numberOfClassTypes === 0) {
                    throw JsonError::decodeFailed(sprintf(
                        'Can\'t populate property "%s" with a JSON object, no class types found in union %s for class %s.',
                        $parameter->name,
                        self::dumpType($parameterType),
                        $class->getName(),
                    ));
                }
                if ($numberOfClassTypes === 1) {
                    $onlyClassName = $classTypes[0]->getName();
                    assert(class_exists($onlyClassName), 'Should have been checked by classTypes()');
                    return self::createObject($value, $onlyClassName);
                }
                throw JsonError::decodeFailed(sprintf(
                    'Unions of multiple object types (%s) are not supported yet for property "%s" in class %s.',
                    implode(', ', array_map(static fn(ReflectionNamedType $t) => $t->getName(), $classTypes)),
                    $parameter->name,
                    $class->getName(),
                ));
            }
        }
        return $value;
    }

    /**
     * @template Key of array-key
     * @param array<Key, ReflectionType> $types
     * @psalm-suppress MoreSpecificReturnType False positive
     * @return array<Key, ReflectionNamedType>
     */
    private static function classTypes(array $types): array
    {
        /** @psalm-suppress LessSpecificReturnStatement False positive */
        return array_filter($types, self::isClassType(...));
    }

    /**
     * @psalm-assert-if-true ReflectionNamedType $type
     */
    private static function isClassType(ReflectionType $type): bool
    {
        assert($type instanceof ReflectionNamedType, 'Intersection types are not supported.');
        return class_exists($type->getName());
    }

    private static function dumpType(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(self::dumpType(...), $type->getTypes()));
        }
        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(self::dumpType(...), $type->getTypes()));
        }
        return 'unknown';
    }

    /**
     * @return 'string'|'int'|'float'|'null'|'bool'|class-string|ArrayOf
     */
    private static function getArrayItemType(ReflectionParameter $parameter): string|ArrayOf
    {
        $attributes = $parameter->getAttributes(ArrayOf::class);
        return match (count($attributes)) {
            0 => throw JsonError::decodeFailed(sprintf(
                'Missing #[ArrayOf] attribute for array property "%s" in class %s.',
                $parameter->name,
                $parameter->getDeclaringClass()?->getName() ?? 'unknown',
            )),
            1 => $attributes[0]->newInstance()->itemType,
            default => throw JsonError::decodeFailed(sprintf(
                'Multiple #[ArrayOf] attributes found for array property "%s" in class %s.',
                $parameter->name,
                $parameter->getDeclaringClass()?->getName() ?? 'unknown',
            )),
        };
    }

    private static function arrayItem(mixed $value, ArrayOf|string $type, int $index): mixed
    {
        return match ($type) {
            'string' => is_string($value) ? $value : throw JsonError::decodeFailed(sprintf(
                'Expected string at index %d of array, got %s.',
                $index,
                gettype($value),
            )),
            'int' => is_int($value) ? $value : throw JsonError::decodeFailed(sprintf(
                'Expected int at index %d of array, got %s.',
                $index,
                gettype($value),
            )),
            'float' => is_float($value) ? $value : throw JsonError::decodeFailed(sprintf(
                'Expected float at index %d of array, got %s.',
                $index,
                gettype($value),
            )),
            'bool' => is_bool($value) ? $value : throw JsonError::decodeFailed(sprintf(
                'Expected bool at index %d of array, got %s.',
                $index,
                gettype($value),
            )),
            'null' => $value === null ? null : throw JsonError::decodeFailed(sprintf(
                'Expected null at index %d of array, got %s.',
                $index,
                gettype($value),
            )),
            default => self::nonTrivialArrayItem($value, $type, $index),
        };
    }

    private static function nonTrivialArrayItem(mixed $value, ArrayOf|string $type, int $index): mixed
    {
        if ($type instanceof ArrayOf) {
            if (!is_array($value)) {
                throw JsonError::decodeFailed(sprintf(
                    'Expected array at index %d of array, got %s.',
                    $index,
                    gettype($value),
                ));
            }
            assert(array_is_list($value));
            $array = [];
            /** @var mixed $itemValue */
            foreach ($value as $j => $itemValue) {
                /** @psalm-suppress MixedAssignment */
                $array[] = self::arrayItem($itemValue, $type->itemType, $j);
            }
            return $array;
        }
        if (is_a($type, UnitEnum::class, true)) {
            return self::createEnumCase($value, $type);
        }
        if (!$value instanceof stdClass) {
            throw JsonError::decodeFailed(sprintf(
                'Expected object at index %d, got %s.',
                $index,
                self::jsonTypeOfPhpValue($value),
            ));
        }
        if (!class_exists($type)) {
            throw JsonError::decodeFailed(sprintf(
                'Class %s referenced by ArrayOf does not exist.',
                $type,
            ));
        }
        return self::createObject($value, $type);
    }

    /**
     * @param class-string<UnitEnum> $enumType
     */
    private static function createEnumCase(mixed $value, string $enumType): UnitEnum
    {
        if (!is_a($enumType, BackedEnum::class, true)) {
            throw JsonError::decodeFailed(sprintf(
                'All enums must be backed, but %s is not a backed enum.',
                $enumType,
            ));
        }
        if (!is_int($value) && !is_string($value)) {
            throw JsonError::decodeFailed(sprintf(
                '%s is not a valid a value of any case of enum %s.',
                json_encode($value, JSON_THROW_ON_ERROR),
                $enumType,
            ));
        }
        $backingType = (new ReflectionEnum($enumType))->getBackingType();
        assert($backingType instanceof ReflectionNamedType);
        $backingTypeName = $backingType->getName();
        if (
            ($backingTypeName === 'int' && !is_int($value)) ||
            ($backingTypeName === 'string' && !is_string($value))
        ) {
            throw JsonError::decodeFailed(sprintf(
                '%s is not a valid a value of any case of enum %s.',
                json_encode($value, JSON_THROW_ON_ERROR),
                $enumType,
            ));
        }
        $enum = $enumType::tryFrom($value);
        if ($enum === null) {
            throw JsonError::decodeFailed(sprintf(
                '%s is not a valid a value of any case of enum %s.',
                json_encode($value, JSON_THROW_ON_ERROR),
                $enumType,
            ));
        }
        return $enum;
    }

    private static function jsonTypeOfPhpValue(mixed $value): string
    {
        return match (gettype($value)) {
            'NULL' => 'null',
            'boolean' => 'boolean',
            'integer', 'double' => 'number',
            'string' => 'string',
            'array' => 'array',
            default => 'object',
        };
    }
}
