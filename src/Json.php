<?php

declare(strict_types=1);

namespace Eventjet\Json;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function assert;
use function class_exists;
use function enum_exists;
use function error_get_last;
use function explode;
use function file;
use function get_object_vars;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function is_subclass_of;
use function json_decode;
use function json_encode;
use function preg_match;
use function property_exists;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use function trim;

final class Json
{
    public static function encode(mixed $value): string
    {
        $json = json_encode(self::preparePropertyValueForEncoding($value));
        // I can't get it to return false
        assert($json !== false);
        return $json;
    }

    /**
     * @template T of object
     * @param T | class-string<T> $value
     * @return T
     */
    public static function decode(string $json, object|string $value): object
    {
        return self::decodeClass($json, $value);
    }

    /**
     * @psalm-suppress MixedInferredReturnType
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    private static function preparePropertyValueForEncoding(mixed $value): string|int|float|bool|array|null
    {
        /** @psalm-suppress MixedReturnStatement */
        return match (true) {
            is_string($value), is_int($value), is_float($value), is_bool($value), $value === null => $value,
            is_array($value) => self::encodeArray($value),
            is_object($value) => self::encodeObject($value),
            default => throw JsonError::encodeFailed(sprintf('Unsupported type "%s"', gettype($value))),
        };
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function encodeArray(array $value): array
    {
        if (!array_is_list($value)) {
            return $value;
        }
        /** @var mixed $item */
        foreach ($value as &$item) {
            $item = self::preparePropertyValueForEncoding($item);
        }
        return $value;
    }

    /**
     * @return array<string, mixed> | string | int
     */
    private static function encodeObject(object $value): array|string|int
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        $raw = get_object_vars($value);
        $out = [];
        /** @var mixed $item */
        foreach ($raw as $key => $item) {
            $jsonKey = self::preparePropertyValueForEncoding($item);
            $out[self::getJsonKeyForProperty(new ReflectionProperty($value, $key))] = $jsonKey;
        }
        return $out;
    }

    private static function getJsonKeyForProperty(ReflectionProperty $property): string
    {
        return ($property->getAttributes(Field::class)[0] ?? null)?->newInstance()->name ?? $property->getName();
    }

    /**
     * @template T of object
     * @param T | class-string<T> $value
     * @return T
     * @psalm-suppress InvalidReturnType
     */
    private static function decodeClass(string $json, object|string $value): object
    {
        $data = json_decode($json, true);
        if ($data === null) {
            throw JsonError::decodeFailed(error_get_last()['message'] ?? null);
        }
        if (!is_array($data)) {
            throw JsonError::decodeFailed(sprintf("Expected JSON object, got %s", gettype($data)));
        }
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($value)) {
            /** @psalm-suppress NoValue */
            self::populateObject($value, $data);
            /** @psalm-suppress NoValue */
            return $value;
        }
        if (!class_exists($value)) {
            throw JsonError::decodeFailed(sprintf('Class "%s" does not exist', $value));
        }
        $object = self::instantiateClass($value, $data);
        assert($object instanceof $value);
        return $object;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function populateObject(object $object, array $data): void
    {
        /** @var mixed $value */
        foreach ($data as $jsonKey => $value) {
            if (is_int($jsonKey)) {
                throw JsonError::decodeFailed(sprintf('Expected JSON object, got array at key "%s"', $jsonKey));
            }
            if (!self::propertyForObjectKeyExists($object, $jsonKey)) {
                continue;
            }
            self::populateProperty($object, $jsonKey, $value);
        }
    }

    private static function propertyForObjectKeyExists(object $object, string $jsonKey): bool
    {
        if (property_exists($object, $jsonKey)) {
            return true;
        }
        foreach ((new ReflectionObject($object))->getProperties() as $property) {
            if (self::getJsonKeyForProperty($property) !== $jsonKey) {
                continue;
            }
            return true;
        }
        return false;
    }

    private static function populateProperty(object $object, string $jsonKey, mixed $value): void
    {
        $property = self::getPropertyNameForJsonKey($object, $jsonKey);
        if (is_array($value)) {
            $itemType = self::getArrayPropertyItemType($object, $property);
            if ($itemType !== null && class_exists($itemType)) {
                /** @var mixed $item */
                foreach ($value as &$item) {
                    if (!is_array($item)) {
                        throw JsonError::decodeFailed(
                            sprintf(
                                'Expected JSON objects for items in property "%s", got %s',
                                $property,
                                gettype($item),
                            ),
                        );
                    }
                    /** @psalm-suppress MixedMethodCall */
                    $newItem = new $itemType();
                    self::populateObject($newItem, $item);
                    $item = $newItem;
                }
            }
            if (!array_is_list($value)) {
                $newValue = self::getPropertyObject($object, $jsonKey);
                self::populateObject($newValue, $value);
                $value = $newValue;
            }
        }
        $object->$property = $value; // @phpstan-ignore-line
    }

    private static function getPropertyNameForJsonKey(object $object, string $key): string
    {
        foreach ((new ReflectionObject($object))->getProperties() as $property) {
            if (self::getJsonKeyForProperty($property) !== $key) {
                continue;
            }
            return $property->getName();
        }
        return $key;
    }

    private static function getArrayPropertyItemType(object $object, string $property): string|null
    {
        return (
            (new ReflectionProperty($object, $property))
                ->getAttributes(Item::class)[0] ?? null
        )?->newInstance()->type;
    }

    private static function getPropertyObject(object $value, string $key): object
    {
        /** @var mixed $propertyValue */
        $propertyValue = $value->$key; // @phpstan-ignore-line
        if (is_object($propertyValue)) {
            return $propertyValue;
        }
        $reflection = new ReflectionProperty($value, $key);
        $type = $reflection->getType();
        if ($type === null) {
            throw JsonError::decodeFailed(sprintf('Property "%s" has no type', $key));
        }
        if (!$type instanceof ReflectionNamedType) {
            throw JsonError::decodeFailed(
                sprintf(
                    'Property "%s" has a union or intersection type (%s), but only simple types are allowed',
                    $key,
                    $type,
                ),
            );
        }
        $type = $type->getName();
        if (!class_exists($type)) {
            throw JsonError::decodeFailed(sprintf('Property "%s" has an unknown type "%s"', $key, $type));
        }
        /** @psalm-suppress MixedMethodCall */
        $object = new $type();
        $value->$key = $object; // @phpstan-ignore-line
        return $object;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed> $data
     */
    private static function instantiateClass(string $class, array $data): object
    {
        $classReflection = new ReflectionClass($class);
        $constructor = $classReflection->getConstructor();
        $arguments = [];
        if ($constructor !== null) {
            $parameters = $constructor->getParameters();
            foreach ($parameters as $parameter) {
                $name = $parameter->getName();
                if (!array_key_exists($name, $data)) {
                    if ($parameter->isOptional()) {
                        /** @psalm-suppress MixedAssignment */
                        $arguments[] = $parameter->getDefaultValue();
                        continue;
                    }
                    throw JsonError::decodeFailed(sprintf('Missing required constructor argument "%s"', $name));
                }
                /** @psalm-suppress MixedAssignment */
                $arguments[] = self::createConstructorArgument($parameter, $data[$name]);
                unset($data[$name]);
            }
        }
        $instance = $classReflection->newInstanceArgs($arguments);
        if ($data !== []) {
            self::populateObject($instance, $data);
        }
        return $instance;
    }

    private static function createConstructorArgument(ReflectionParameter $parameter, mixed $jsonValue): mixed
    {
        $type = $parameter->getType();
        if ($type === null) {
            return $jsonValue;
        }
        if ($type instanceof ReflectionNamedType) {
            return self::createConstructorArgumentForNamedType($parameter, $jsonValue);
        }
        if ($type instanceof ReflectionUnionType) {
            return self::createConstructorArgumentForUnionType();
        }
        return self::createConstructorArgumentForIntersectionType();
    }

    private static function createConstructorArgumentForNamedType(ReflectionParameter $parameter, mixed $value): mixed
    {
        if ($value === null && $parameter->allowsNull()) {
            return null;
        }
        $type = $parameter->getType();
        assert($type instanceof ReflectionNamedType);
        $typeName = $type->getName();
        $paramName = $parameter->getName();
        if ($typeName === 'array') {
            return self::createConstructorArgumentForArrayType($parameter, $value);
        }
        if ($type->isBuiltin()) {
            return $value;
        }
        if (enum_exists($typeName)) {
            if (!is_subclass_of($typeName, BackedEnum::class)) {
                throw JsonError::decodeFailed(
                    sprintf(
                        'Only backed enums are allowed as constructor arguments, but "%s" is not backed',
                        $typeName,
                    ),
                );
            }
            /** @psalm-suppress ArgumentTypeCoercion There is no "enum-string" type */
            return self::createConstructorArgumentForEnumType($value, $paramName, $typeName);
        }
        if (!class_exists($typeName)) {
            $class = $parameter->getDeclaringClass();
            assert($class !== null);
            throw JsonError::decodeFailed(
                sprintf(
                    'The type of the constructor parameter "%s" for class %s is "%s", but this class does not exist',
                    $paramName,
                    $class->getName(),
                    $typeName,
                ),
            );
        }
        if (!is_array($value)) {
            throw JsonError::decodeFailed(
                sprintf(
                    'Expected array<string, mixed> for parameter "%s", got %s',
                    $paramName,
                    gettype($value),
                ),
            );
        }
        return self::instantiateClass($typeName, $value);
    }

    private static function createConstructorArgumentForUnionType(): mixed
    {
        throw JsonError::decodeFailed('Union types are not supported');
    }

    private static function createConstructorArgumentForIntersectionType(): mixed
    {
        throw JsonError::decodeFailed('Intersection types are not supported');
    }

    /**
     * @return list<array{string, string}>
     */
    private static function parseDocTags(string $doc): array
    {
        $tags = [];
        $lines = explode("\n", $doc);
        $currentTag = null;
        $currentTagContent = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '* ')) {
                $line = substr($line, 2);
            }
            if ($line === '*/') {
                // @infection-ignore-all This one is impossible to kill
                break;
            }
            // @infection-ignore-all Skip preg_match mutants for now
            $result = preg_match('/^@(\w+)(?:\s+(.*))?/', $line, $matches);
            if ($result === 1) {
                if ($currentTag !== null) {
                    $tags[] = [$currentTag, $currentTagContent];
                }
                $currentTag = $matches[1];
                $currentTagContent = $matches[2] ?? '';
                continue;
            }
            if ($currentTag === null) {
                continue;
            }
            $currentTagContent .= $line;
        }
        if ($currentTag !== null) {
            $tags[] = [$currentTag, $currentTagContent];
        }
        return $tags;
    }

    /**
     * @param list<array{string, string}> $tags
     */
    private static function findParamTagType(array $tags, string $param): string|null
    {
        foreach ($tags as [$tag, $content]) {
            if ($tag !== 'param') {
                continue;
            }
            // @infection-ignore-all Skip preg_match mutants for now
            $result = preg_match('/^(?<type>.+)\s+\$(?<name>\S+)/', $content, $matches);
            if ($result !== 1) {
                continue;
            }
            if ($matches['name'] !== $param) {
                continue;
            }
            return $matches['type'];
        }
        return null;
    }

    private static function nonNullable(string $type): string
    {
        // @infection-ignore-all Skip preg_match mutants for now
        $result = preg_match('/^(null\s*\|\s*)?(?<type>.+?)(\s*\|\s*null)?$/', $type, $matches);
        if ($result !== 1) {
            return $type;
        }
        return $matches['type'];
    }

    private static function listItemType(string $type, string $param, string $class): string
    {
        // @infection-ignore-all Skip preg_match mutants for now
        $result = preg_match('/^list<\s*(?<type>.+?)\s*>$/', $type, $matches);
        if ($result !== 1) {
            $template = 'The doc type for the constructor argument %s of %s is wrong. Expected "list<...>", got "%s"';
            throw JsonError::decodeFailed(sprintf($template, $param, $class, $type));
        }
        return $matches['type'];
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function aliasToFqcn(string $alias, ReflectionClass $class): string
    {
        $namespace = $class->getNamespaceName();
        if (str_starts_with($alias, '\\')) {
            return $alias;
        }
        if (str_contains($alias, '\\')) {
            return $namespace . '\\' . $alias;
        }
        $classFile = $class->getFileName();
        // Skip this check for now. If we ever encounter it (e.g. when dealing with built-in classes), we can add it
        // later. Should be pretty easy.
        assert($classFile !== false);
        $useStatements = self::parseUseStatements($classFile);
        if (isset($useStatements[$alias])) {
            return $useStatements[$alias];
        }
        return $namespace . '\\' . $alias;
    }

    /**
     * @return array<string, string>
     */
    private static function parseUseStatements(string $file): array
    {
        $useStatements = [];
        $lines = file($file);
        assert($lines !== false);
        foreach ($lines as $line) {
            $result = preg_match('/use\s+(?<ns>.+\\\\)?(?<class>.+?)(\s+as\s+(?<alias>.+))?;/', $line, $matches);
            if ($result !== 1) {
                continue;
            }
            $useStatements[$matches['alias'] ?? $matches['class']] = $matches['ns'] . $matches['class'];
        }
        return $useStatements;
    }

    /**
     * @return list<mixed> | array<array-key, mixed>
     */
    private static function createConstructorArgumentForArrayType(
        ReflectionParameter $parameter,
        mixed $value,
    ): array|null {
        if ($value === null && $parameter->allowsNull()) {
            return null;
        }
        if (!is_array($value)) {
            throw JsonError::decodeFailed(
                sprintf('Expected array for parameter "%s", got %s', $parameter->getName(), gettype($value)),
            );
        }
        return array_is_list($value)
            ? self::createConstructorArgumentForListType($parameter, $value)
            : self::createConstructorArgumentForMapType($parameter, $value);
    }

    /**
     * @param list<mixed> $value
     * @return list<mixed>
     */
    private static function createConstructorArgumentForListType(ReflectionParameter $parameter, array $value): array
    {
        $paramName = $parameter->getName();
        $constructorDoc = $parameter->getDeclaringFunction()->getDocComment();
        $class = $parameter->getDeclaringClass();
        assert($class !== null);
        $notDocumented = JsonError::decodeFailed(
            sprintf(
                'The type of the constructor parameter "%s" for class %s is "array", but its shape is not documented',
                $paramName,
                $class->getName(),
            ),
        );
        if ($constructorDoc === false) {
            throw $notDocumented;
        }
        $serializedType = self::findParamTagType(self::parseDocTags($constructorDoc), $paramName);
        if ($serializedType === null) {
            throw $notDocumented;
        }
        $serializedType = self::listItemType(self::nonNullable($serializedType), $paramName, $class->getName());
        $serializedType = self::aliasToFqcn($serializedType, $class);
        $items = $value;
        if (class_exists($serializedType)) {
            $items = [];
            /** @var mixed $item */
            foreach ($value as $item) {
                if (!is_array($item)) {
                    throw JsonError::decodeFailed(
                        sprintf(
                            'Expected JSON objects for items in property "%s", got %s',
                            $paramName,
                            gettype($item),
                        ),
                    );
                }
                $items[] = self::instantiateClass($serializedType, $item);
            }
        }
        return $items;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function createConstructorArgumentForMapType(ReflectionParameter $parameter, array $value): array
    {
        $paramName = $parameter->getName();
        $valueType = self::getMapValueType($parameter);
        if ($valueType === null) {
            $class = $parameter->getDeclaringClass();
            assert($class !== null);
            throw JsonError::decodeFailed(
                sprintf(
                    'The type of the constructor parameter "%s" for class %s is "array", but its shape is not '
                    . 'documented',
                    $paramName,
                    $class->getName(),
                ),
            );
        }
        if (!class_exists($valueType)) {
            return $value;
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_array($item)) {
                throw JsonError::decodeFailed(
                    sprintf(
                        'Expected an array for the value of key "%s" in parameter "%s", got %s',
                        $key,
                        $paramName,
                        gettype($item),
                    ),
                );
            }
            $result[$key] = self::instantiateClass($valueType, $item);
        }
        return $result;
    }

    /**
     * @template T of BackedEnum
     * @param class-string<T> $typeName
     * @return T
     */
    private static function createConstructorArgumentForEnumType(
        mixed $value,
        string $paramName,
        string $typeName,
    ): BackedEnum {
        if (!is_string($value) && !is_int($value)) {
            throw JsonError::decodeFailed(
                sprintf(
                    'Expected string or int for parameter "%s", got %s',
                    $paramName,
                    gettype($value),
                ),
            );
        }
        /**
         * @psalm-suppress MixedAssignment
         * @psalm-suppress MixedMethodCall
         */
        $case = $typeName::tryFrom($value);
        if ($case === null) {
            /**
             * @psalm-suppress MixedArgument
             * @psalm-suppress MixedMethodCall
             */
            throw JsonError::decodeFailed(
                sprintf(
                    '"%s" is not a valid value for enum %s. Valid values are: %s',
                    $value,
                    $typeName,
                    implode(', ', array_map(static fn(BackedEnum $case) => $case->value, $typeName::cases())),
                ),
            );
        }
        return $case;
    }

    private static function getMapValueType(ReflectionParameter $parameter): string|null
    {
        $constructorDoc = $parameter->getDeclaringFunction()->getDocComment();
        if ($constructorDoc === false) {
            return null;
        }
        $paramName = $parameter->getName();
        $class = $parameter->getDeclaringClass();
        assert($class !== null);
        $serializedType = self::findParamTagType(self::parseDocTags($constructorDoc), $paramName);
        if ($serializedType === null) {
            return null;
        }
        $valueType = self::mapValueType(self::nonNullable($serializedType));
        if ($valueType === null) {
            throw JsonError::decodeFailed(
                sprintf(
                    'The type of the constructor parameter "%s" for class %s is wrong. Expected "array<K, V>", got '
                    . '"%s"',
                    $paramName,
                    $class->getName(),
                    $serializedType,
                ),
            );
        }
        return self::aliasToFqcn($valueType, $class);
    }

    private static function mapValueType(string $typeString): string|null
    {
        // @infection-ignore-all Skip preg_match mutants for now
        $result = preg_match('/^array<.+,\s*(.+)>$/', $typeString, $matches);
        if ($result !== 1) {
            return null;
        }
        return $matches[1];
    }
}
