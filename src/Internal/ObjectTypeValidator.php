<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

use function array_key_exists;
use function enum_exists;
use function sprintf;

/** @internal */
final class ObjectTypeValidator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @return array<string, ListType|MapType|TupleType|null>|DecodeError
     * @throws ReflectionException
     */
    public static function validate(ReflectionClass $class, array $values): array|DecodeError
    {
        $targetError = RootTypeValidator::validate($class);

        if ($targetError !== null) {
            return $targetError;
        }

        $className = $class->getName();
        $collections = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($parameter->isVariadic()) {
                return DecodeError::nonInstantiableTarget($className, sprintf(
                    'Constructor parameter %s is variadic. JSON members bind to individual named arguments, not variadic argument lists.',
                    $name,
                ));
            }

            if ($type === null) {
                return DecodeError::nonInstantiableField(
                    $className,
                    $name,
                    'no type',
                    'declaration',
                    '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
                );
            }

            $collection = FieldTypeValidator::validate($className, $parameter);

            if ($collection instanceof DecodeError) {
                return $collection;
            }

            $collections[$name] = $collection;

            if (!$class->hasProperty($name)) {
                return self::unrecoverableConstructorParameter($className, $name);
            }

            $property = $class->getProperty($name);

            if (!$property->isPublic() || $property->isStatic()) {
                return self::unrecoverableConstructorParameter($className, $name);
            }

            if ($type instanceof ReflectionNamedType) {
                $typeName = FieldTypeNameResolver::resolve($parameter, $type);

                if (array_key_exists($name, $values) && !enum_exists($typeName)) {
                    /** @var mixed $value */
                    $value = $values[$name];
                    $valueMatchesType = ValueTypeMatcher::matches($value, $type);

                    if (!$valueMatchesType) {
                        $expectedType = $typeName;

                        if ($type->allowsNull() && $expectedType !== 'null') {
                            $expectedType .= '|null';
                        }

                        return DecodeError::fieldTypeMismatch($className, $name, $expectedType, $value);
                    }
                }
            }
        }

        return $collections;
    }

    /** @param class-string $class */
    private static function unrecoverableConstructorParameter(string $class, string $parameter): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Constructor parameter %s has no same-named declared public instance property. The target class does not expose a stable JSON member from which the argument can be recovered.',
            $parameter,
        ));
    }
}
