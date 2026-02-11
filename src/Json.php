<?php

declare(strict_types=1);

namespace Eventjet\Json;

use BackedEnum;
use Eventjet\Json\Exception\InvalidEnumValueException;
use Eventjet\Json\Exception\InvalidJsonException;
use Eventjet\Json\Exception\MissingFieldException;
use Eventjet\Json\Exception\TypeMismatchException;
use Eventjet\Json\Exception\TypeParseException;
use Eventjet\Json\Exception\UnsupportedTypeException;
use Eventjet\Json\Type\ClassType;
use Eventjet\Json\Type\ListType;
use Eventjet\Json\Type\MapType;
use Eventjet\Json\Type\ParsedType;
use Eventjet\Json\Type\PrimitiveType;
use Eventjet\Json\Type\TypeParser;
use Eventjet\Json\Type\UnionType;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;

use function array_is_list;
use function array_key_exists;
use function assert;
use function class_exists;
use function count;
use function enum_exists;
use function get_debug_type;
use function in_array;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;

use const JSON_ERROR_NONE;

final class Json
{
    /**
     * Decode JSON into PHP values.
     *
     * The $type parameter supports a subset of PHPStan/Psalm/PhpStorm-compatible type syntax:
     * - Primitives: `'string'`, `'int'`, `'float'`, `'bool'`, `'null'`
     * - Class names: `MyClass::class` or `'My\Namespace\MyClass'`
     * - Backed enums: `MyEnum::class`
     * - List types: `'list<string>'`, `'list<MyClass>'`, `'list<list<int>>'`
     * - Map types: `'array<string, int>'`, `'array<string, MyClass>'`
     * - Union types: `'string|null'`, `'list<string>|array<string, int>'`
     *
     * @template T of object
     * @param class-string<T>|null $type
     * @return ($type is null ? mixed : T)
     * @throws Exception\JsonDecodeException
     */
    public static function decode(string $json, string|null $type = null): mixed
    {
        /** @psalm-suppress MixedAssignment json_decode returns mixed by design */
        $decoded = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJsonException(json_last_error_msg());
        }

        if ($type === null) {
            /** @psalm-suppress MixedReturnStatement Return type is mixed when $type is null */
            return self::decodeWithoutType($decoded);
        }

        $parser = new TypeParser();
        $parsedType = $parser->parse($type);

        /** @psalm-suppress MixedReturnStatement Return type depends on $type which is validated at runtime */
        return self::decodeWithParsedType($decoded, $parsedType, 'root', 'root');
    }

    private static function decodeWithoutType(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw new UnsupportedTypeException(
            sprintf('Cannot decode JSON %s without a type argument', get_debug_type($value)),
        );
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    private static function decodeBackedEnum(mixed $value, string $enumClass): BackedEnum
    {
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();
        assert(
            $backingType instanceof ReflectionNamedType,
            'BackedEnum always has a backing type; unbacked enums are rejected in decodeWithType()',
        );
        $backingTypeName = $backingType->getName();

        if ($backingTypeName === 'string') {
            if (!is_string($value)) {
                throw new TypeMismatchException(
                    sprintf('Expected string for enum %s, got %s', $enumClass, get_debug_type($value)),
                );
            }
        } else {
            if (!is_int($value)) {
                throw new TypeMismatchException(
                    sprintf('Expected int for enum %s, got %s', $enumClass, get_debug_type($value)),
                );
            }
        }

        $case = $enumClass::tryFrom($value);

        if ($case === null) {
            $valueStr = is_string($value) ? $value : (string)$value;
            throw new InvalidEnumValueException(
                sprintf('Invalid value %s for enum %s', $valueStr, $enumClass),
            );
        }

        return $case;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     * @psalm-suppress InvalidReturnType T may implement JsonSerializable; runtime guarantees correct type
     */
    private static function decodeClass(mixed $value, string $className): object
    {
        if (!$value instanceof stdClass) {
            /** @psalm-suppress DocblockTypeContradiction T may implement JsonSerializable */
            if (is_a($className, JsonSerializable::class, true)) {
                /** @psalm-suppress InvalidReturnStatement T may implement JsonSerializable */
                return self::decodeJsonSerializable($value, new ReflectionClass($className), $className);
            }
            throw new TypeMismatchException(
                sprintf('Expected object for %s, got %s', $className, get_debug_type($value)),
            );
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            /** @psalm-suppress MixedAssignment Constructor args are intentionally mixed, validated at runtime */
            $args[] = self::resolveParameter($param, $value, $className);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param class-string<T> $className
     * @return T
     */
    private static function decodeJsonSerializable(
        mixed $value,
        ReflectionClass $reflection,
        string $className,
    ): object {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            throw new UnsupportedTypeException(
                sprintf('JsonSerializable class %s has no constructor', $className),
            );
        }

        $params = $constructor->getParameters();
        $requiredParams = [];
        foreach ($params as $param) {
            if (!$param->isOptional()) {
                $requiredParams[] = $param;
            }
        }

        if (count($requiredParams) !== 1) {
            throw new UnsupportedTypeException(
                sprintf(
                    'JsonSerializable class %s must have exactly one required constructor parameter to decode from a non-object value, got %d',
                    $className,
                    count($requiredParams),
                ),
            );
        }

        $requiredParam = $requiredParams[0];
        $type = $requiredParam->getType();
        /** @psalm-suppress MixedAssignment Decoded value is intentionally mixed */
        $decoded = match (true) {
            $type instanceof ReflectionNamedType => self::resolveNamedType(
                $type,
                $value,
                $requiredParam->getName(),
                $className,
                $requiredParam,
            ),
            $type instanceof ReflectionUnionType => self::resolveUnionType(
                $type,
                $value,
                $requiredParam->getName(),
                $className,
                $requiredParam,
            ),
            default => $value,
        };

        $args = [];
        foreach ($params as $param) {
            /** @psalm-suppress MixedAssignment Constructor args are intentionally mixed */
            if ($param->getName() === $requiredParam->getName()) {
                $args[] = $decoded;
            } else {
                $args[] = $param->getDefaultValue();
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    private static function resolveParameter(ReflectionParameter $param, stdClass $data, string $className): mixed
    {
        $name = $param->getName();
        $hasValue = array_key_exists($name, (array)$data);

        if (!$hasValue) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new MissingFieldException(
                sprintf('Missing required field "%s" for class %s', $name, $className),
            );
        }

        /** @psalm-suppress MixedAssignment stdClass property access returns mixed, validated below */
        $value = $data->{$name};
        $type = $param->getType();

        if ($type === null) {
            return $value;
        }

        if ($type instanceof ReflectionUnionType) {
            return self::resolveUnionType($type, $value, $name, $className, $param);
        }

        if ($type instanceof ReflectionNamedType) {
            return self::resolveNamedType($type, $value, $name, $className, $param);
        }

        throw new UnsupportedTypeException(
            sprintf('Unsupported type for field "%s" in class %s', $name, $className),
        );
    }

    private static function resolveUnionType(
        ReflectionUnionType $type,
        mixed $value,
        string $fieldName,
        string $className,
        ReflectionParameter|null $param = null,
    ): mixed {
        $namedTypes = $type->getTypes();
        $classTypes = [];
        $primitiveTypes = [];
        $hasArray = false;

        foreach ($namedTypes as $namedType) {
            assert(
                $namedType instanceof ReflectionNamedType,
                'PHP union types can only contain named types (ReflectionNamedType)',
            );
            $typeName = $namedType->getName();
            if ($namedType->isBuiltin()) {
                if ($typeName === 'array') {
                    $hasArray = true;
                } else {
                    $primitiveTypes[] = $typeName;
                }
            } elseif (class_exists($typeName) || is_a($typeName, BackedEnum::class, true)) {
                $classTypes[] = $typeName;
            }
        }

        if (count($classTypes) > 1) {
            throw new UnsupportedTypeException(
                sprintf('Class unions are not supported for field "%s" in class %s', $fieldName, $className),
            );
        }

        if ($value === null && in_array('null', $primitiveTypes, true)) {
            return null;
        }

        if (is_string($value) && in_array('string', $primitiveTypes, true)) {
            return $value;
        }

        if (is_int($value) && in_array('int', $primitiveTypes, true)) {
            return $value;
        }

        if (is_float($value) && in_array('float', $primitiveTypes, true)) {
            return $value;
        }

        if (is_bool($value) && in_array('bool', $primitiveTypes, true)) {
            return $value;
        }

        if ($value instanceof stdClass && count($classTypes) === 1) {
            return self::decodeClass($value, $classTypes[0]);
        }

        if (!$value instanceof stdClass && count($classTypes) === 1
            && is_a($classTypes[0], JsonSerializable::class, true)) {
            return self::decodeClass($value, $classTypes[0]);
        }

        if ((is_array($value) || $value instanceof stdClass) && $hasArray && $param !== null) {
            return self::resolveArrayType($param, $value, $fieldName, $className);
        }

        throw new TypeMismatchException(
            sprintf(
                'Type mismatch for field "%s" in class %s: expected one of union types, got %s',
                $fieldName,
                $className,
                get_debug_type($value),
            ),
        );
    }

    private static function resolveNamedType(
        ReflectionNamedType $type,
        mixed $value,
        string $fieldName,
        string $className,
        ReflectionParameter|null $param = null,
    ): mixed {
        $typeName = $type->getName();

        if ($type->allowsNull() && $value === null) {
            return null;
        }

        if ($type->isBuiltin()) {
            if ($typeName === 'array') {
                if ($param === null) {
                    throw new UnsupportedTypeException(
                        sprintf('Array type is not supported for field "%s" in class %s', $fieldName, $className),
                    );
                }
                return self::resolveArrayType($param, $value, $fieldName, $className);
            }
            return self::resolvePrimitiveType($typeName, $value, $fieldName, $className);
        }

        if (is_a($typeName, BackedEnum::class, true)) {
            return self::decodeBackedEnum($value, $typeName);
        }

        if (class_exists($typeName)) {
            return self::decodeClass($value, $typeName);
        }

        throw new UnsupportedTypeException(
            sprintf('Unknown type %s for field "%s" in class %s', $typeName, $fieldName, $className),
        );
    }

    private static function resolvePrimitiveType(
        string $typeName,
        mixed $value,
        string $fieldName,
        string $className,
    ): mixed {
        return match ($typeName) {
            'string' => is_string($value)
                ? $value
                : throw new TypeMismatchException(
                    sprintf(
                        'Expected string for field "%s" in class %s, got %s',
                        $fieldName,
                        $className,
                        get_debug_type($value),
                    ),
                ),
            'int' => is_int($value)
                ? $value
                : throw new TypeMismatchException(
                    sprintf(
                        'Expected int for field "%s" in class %s, got %s',
                        $fieldName,
                        $className,
                        get_debug_type($value),
                    ),
                ),
            'float' => is_float($value) || is_int($value)
                ? (float)$value
                : throw new TypeMismatchException(
                    sprintf(
                        'Expected float for field "%s" in class %s, got %s',
                        $fieldName,
                        $className,
                        get_debug_type($value),
                    ),
                ),
            'bool' => is_bool($value)
                ? $value
                : throw new TypeMismatchException(
                    sprintf(
                        'Expected bool for field "%s" in class %s, got %s',
                        $fieldName,
                        $className,
                        get_debug_type($value),
                    ),
                ),
            default => throw new UnsupportedTypeException(
                sprintf('Unsupported primitive type %s for field "%s" in class %s', $typeName, $fieldName, $className),
            ),
        };
    }

    /**
     * @return list<mixed>|array<string, mixed>
     */
    private static function resolveArrayType(
        ReflectionParameter $param,
        mixed $value,
        string $fieldName,
        string $className,
    ): array {
        $docComment = $param->getDeclaringFunction()->getDocComment();
        if ($docComment === false) {
            throw new UnsupportedTypeException(
                sprintf(
                    'Array field "%s" in class %s requires a @param docblock annotation with list<T> or array<string, T> type',
                    $fieldName,
                    $className,
                ),
            );
        }

        $docblockParser = new DocblockParser();
        $typeString = $docblockParser->getParamType($docComment, $fieldName);
        if ($typeString === null) {
            throw new UnsupportedTypeException(
                sprintf(
                    'Array field "%s" in class %s requires a @param docblock annotation with list<T> or array<string, T> type',
                    $fieldName,
                    $className,
                ),
            );
        }

        $resolver = new UseStatementResolver();
        $parser = new TypeParser(static fn(string $name): string => $resolver->resolve($name, $className));
        try {
            $parsedType = $parser->parse($typeString);
        } catch (TypeParseException $e) {
            throw new UnsupportedTypeException(
                sprintf(
                    'Array field "%s" in class %s: %s',
                    $fieldName,
                    $className,
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }

        $mapType = self::extractMapTypeFromParsed($parsedType);
        $listType = self::extractListTypeFromParsed($parsedType);

        // Handle unions like list<T>|array<string, T> by checking the actual value type
        if ($value instanceof stdClass && $mapType !== null) {
            return self::decodeMap($value, $mapType->valueType, $fieldName, $className);
        }

        if (is_array($value) && $listType !== null) {
            if ($value !== [] && !array_is_list($value)) {
                throw new TypeMismatchException(
                    sprintf('Expected list for field "%s" in class %s, got associative array', $fieldName, $className),
                );
            }
            return self::decodeList($value, $listType->inner, $fieldName, $className);
        }

        // Provide specific error messages based on what types were expected
        if ($mapType !== null && $listType !== null) {
            throw new TypeMismatchException(
                sprintf('Expected array or object for field "%s" in class %s, got %s', $fieldName, $className, get_debug_type($value)),
            );
        }

        if ($mapType !== null) {
            throw new TypeMismatchException(
                sprintf('Expected object for field "%s" in class %s, got %s', $fieldName, $className, get_debug_type($value)),
            );
        }

        if ($listType !== null) {
            throw new TypeMismatchException(
                sprintf('Expected array for field "%s" in class %s, got %s', $fieldName, $className, get_debug_type($value)),
            );
        }

        throw new UnsupportedTypeException(
            sprintf(
                'Array field "%s" in class %s must use list<T> or array<string, T> syntax, got "%s"',
                $fieldName,
                $className,
                $typeString,
            ),
        );
    }

    /**
     * Extract a ListType from a parsed type, handling unions like list<T>|null.
     */
    private static function extractListTypeFromParsed(ParsedType $type): ListType|null
    {
        if ($type instanceof ListType) {
            return $type;
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $innerType) {
                if ($innerType instanceof ListType) {
                    return $innerType;
                }
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $value
     * @return list<mixed>
     */
    private static function decodeList(array $value, ParsedType $innerType, string $fieldName, string $className): array
    {
        $result = [];
        /** @psalm-suppress MixedAssignment Array items are intentionally mixed */
        foreach ($value as $index => $item) {
            $result[] = self::decodeWithParsedType($item, $innerType, "{$fieldName}[{$index}]", $className);
        }
        return $result;
    }

    private static function decodeWithParsedType(
        mixed $value,
        ParsedType $type,
        string $fieldName,
        string $contextClass,
    ): mixed {
        if ($type instanceof PrimitiveType) {
            return self::decodeParsedPrimitive($value, $type, $fieldName, $contextClass);
        }

        if ($type instanceof ClassType) {
            $typeName = $type->className;
            if (is_a($typeName, BackedEnum::class, true)) {
                return self::decodeBackedEnum($value, $typeName);
            }
            if (enum_exists($typeName)) {
                throw new UnsupportedTypeException(
                    sprintf('Enum %s is not backed', $typeName),
                );
            }
            if (class_exists($typeName)) {
                return self::decodeClass($value, $typeName);
            }
            throw new UnsupportedTypeException(
                sprintf('Class %s does not exist for field "%s" in class %s', $typeName, $fieldName, $contextClass),
            );
        }

        if ($type instanceof ListType) {
            if (!is_array($value)) {
                throw new TypeMismatchException(
                    sprintf(
                        'Expected array for field "%s" in class %s, got %s',
                        $fieldName,
                        $contextClass,
                        get_debug_type($value),
                    ),
                );
            }
            if ($value !== [] && !array_is_list($value)) {
                throw new TypeMismatchException(
                    sprintf('Expected list for field "%s" in class %s, got associative array', $fieldName, $contextClass),
                );
            }
            return self::decodeList($value, $type->inner, $fieldName, $contextClass);
        }

        if ($type instanceof MapType) {
            if (!$value instanceof stdClass) {
                throw new TypeMismatchException(
                    sprintf(
                        'Expected object for field "%s" in class %s, got %s',
                        $fieldName,
                        $contextClass,
                        get_debug_type($value),
                    ),
                );
            }
            return self::decodeMap($value, $type->valueType, $fieldName, $contextClass);
        }

        if ($type instanceof UnionType) {
            return self::decodeParsedUnion($value, $type, $fieldName, $contextClass);
        }

        throw new UnsupportedTypeException(
            sprintf('Unsupported parsed type for field "%s" in class %s', $fieldName, $contextClass),
        );
    }

    private static function decodeParsedPrimitive(
        mixed $value,
        PrimitiveType $type,
        string $fieldName,
        string $contextClass,
    ): mixed {
        return match ($type->name) {
            'string' => is_string($value)
                ? $value
                : throw new TypeMismatchException(
                    sprintf('Expected string for field "%s" in class %s, got %s', $fieldName, $contextClass, get_debug_type($value)),
                ),
            'int' => is_int($value)
                ? $value
                : throw new TypeMismatchException(
                    sprintf('Expected int for field "%s" in class %s, got %s', $fieldName, $contextClass, get_debug_type($value)),
                ),
            'float' => is_float($value) || is_int($value)
                ? (float)$value
                : throw new TypeMismatchException(
                    sprintf('Expected float for field "%s" in class %s, got %s', $fieldName, $contextClass, get_debug_type($value)),
                ),
            'bool' => is_bool($value)
                ? $value
                : throw new TypeMismatchException(
                    sprintf('Expected bool for field "%s" in class %s, got %s', $fieldName, $contextClass, get_debug_type($value)),
                ),
            'null' => $value === null
                ? null
                : throw new TypeMismatchException(
                    sprintf('Expected null for field "%s" in class %s, got %s', $fieldName, $contextClass, get_debug_type($value)),
                ),
            default => throw new UnsupportedTypeException(
                sprintf('Unsupported primitive type %s for field "%s" in class %s', $type->name, $fieldName, $contextClass),
            ),
        };
    }

    private static function decodeParsedUnion(
        mixed $value,
        UnionType $type,
        string $fieldName,
        string $contextClass,
    ): mixed {
        $primitiveTypes = [];
        $classTypes = [];
        $listTypes = [];
        $mapTypes = [];

        foreach ($type->types as $innerType) {
            if ($innerType instanceof PrimitiveType) {
                $primitiveTypes[] = $innerType;
            } elseif ($innerType instanceof ClassType) {
                $classTypes[] = $innerType;
            } elseif ($innerType instanceof ListType) {
                $listTypes[] = $innerType;
            } elseif ($innerType instanceof MapType) {
                $mapTypes[] = $innerType;
            }
        }

        if (count($classTypes) > 1) {
            throw new UnsupportedTypeException(
                sprintf('Class unions are not supported for field "%s" in class %s', $fieldName, $contextClass),
            );
        }

        foreach ($primitiveTypes as $primitiveType) {
            if ($primitiveType->name === 'null' && $value === null) {
                return null;
            }
            if ($primitiveType->name === 'string' && is_string($value)) {
                return $value;
            }
            if ($primitiveType->name === 'int' && is_int($value)) {
                return $value;
            }
            if ($primitiveType->name === 'float' && (is_float($value) || is_int($value))) {
                return (float)$value;
            }
            if ($primitiveType->name === 'bool' && is_bool($value)) {
                return $value;
            }
        }

        if ($value instanceof stdClass && count($classTypes) === 1) {
            $typeName = $classTypes[0]->className;
            if (is_a($typeName, BackedEnum::class, true)) {
                return self::decodeBackedEnum($value, $typeName);
            }
            if (class_exists($typeName)) {
                return self::decodeClass($value, $typeName);
            }
        }

        if (!$value instanceof stdClass && count($classTypes) === 1
            && is_a($classTypes[0]->className, JsonSerializable::class, true)) {
            return self::decodeClass($value, $classTypes[0]->className);
        }

        if ($value instanceof stdClass && count($mapTypes) === 1) {
            return self::decodeMap($value, $mapTypes[0]->valueType, $fieldName, $contextClass);
        }

        if (is_array($value) && count($listTypes) === 1) {
            if ($value !== [] && !array_is_list($value)) {
                throw new TypeMismatchException(
                    sprintf('Expected list for field "%s" in class %s, got associative array', $fieldName, $contextClass),
                );
            }
            return self::decodeList($value, $listTypes[0]->inner, $fieldName, $contextClass);
        }

        throw new TypeMismatchException(
            sprintf(
                'Type mismatch for field "%s" in class %s: expected one of union types, got %s',
                $fieldName,
                $contextClass,
                get_debug_type($value),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeMap(
        stdClass $value,
        ParsedType $valueType,
        string $fieldName,
        string $className,
    ): array {
        $result = [];
        /** @psalm-suppress MixedAssignment Object properties are intentionally mixed */
        foreach ((array)$value as $key => $item) {
            /** @var string $key */
            $result[$key] = self::decodeWithParsedType($item, $valueType, "{$fieldName}[{$key}]", $className);
        }
        return $result;
    }

    /**
     * Extract a MapType from a parsed type, handling unions like array<string, T>|null.
     */
    private static function extractMapTypeFromParsed(ParsedType $type): MapType|null
    {
        if ($type instanceof MapType) {
            return $type;
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $innerType) {
                if ($innerType instanceof MapType) {
                    return $innerType;
                }
            }
        }

        return null;
    }
}
