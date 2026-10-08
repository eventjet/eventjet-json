<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;
use function sprintf;

/** @internal */
final class ObjectTypeValidator
{
    /** @var array<class-string, ConstructorValidationPlan> */
    private static array $plans = [];

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @return array<string, ListType|MapType|TupleType|FieldCollectionUnionType|null>|DecodeError
     * @phpstan-impure
     * @throws ReflectionException
     */
    public static function validate(ReflectionClass $class, array $values, string $path): array|DecodeError
    {
        $className = $class->getName();
        $plan = self::$plans[$className] ?? null;
        if ($plan !== null) {
            return $plan->validate($values, $path);
        }

        $fields = [];
        $cacheable = true;
        $collections = [];

        foreach (ConstructorParameters::resolve($class) as $parameter) {
            $name = $parameter->name;
            $type = $parameter->type;

            if ($parameter->variadic) {
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

            $resolved = FieldTypeValidator::validate($className, $parameter->reflection);
            $cacheable = $cacheable && $resolved !== null;
            $collection = $resolved === false ? null : $resolved;

            if ($collection instanceof DecodeError) {
                return $collection;
            }

            $collections[$name] = $collection;

            if (!$parameter->recoverable) {
                return self::unrecoverableConstructorParameter($className, $name);
            }

            $field = ConstructorValueValidator::forParameter($parameter, $values);
            if ($field !== null) {
                $fields[$name] = $field;
                if (array_key_exists($name, $values)) {
                    $error = $field->validate($className, $name, $values[$name], $path);
                    if ($error !== null) {
                        return $error;
                    }
                }
            }
        }

        if ($cacheable) {
            self::$plans[$className] = new ConstructorValidationPlan($className, $fields, $collections);
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
