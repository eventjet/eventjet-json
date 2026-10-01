<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ReflectionClass;
use ReflectionNamedType;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function ltrim;
use function str_starts_with;

use const JSON_ERROR_NONE;

final class Json
{
    /**
     * @template T of object
     * @param string $json
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $json, string $class): object
    {
        /** @var mixed $values */
        $values = json_decode($json, associative: true);

        if ($values === null && json_last_error() !== JSON_ERROR_NONE) {
            return DecodeError::invalidJson(json_last_error_msg());
        }

        // Associative decoding loses the root shape, so inspect the validated JSON.
        if (!is_array($values) || str_starts_with(ltrim($json), '[')) {
            return DecodeError::unexpectedRootValue($values);
        }

        try {
            $reflection = new ReflectionClass($class);

            $typeError = self::validateFieldTypes($reflection, $values);

            if ($typeError !== null) {
                return $typeError;
            }

            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            return new $class(...$values);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     */
    private static function validateFieldTypes(ReflectionClass $class, array $values): DecodeError|null
    {
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $values)) {
                /** @var mixed $value */
                $value = $values[$name];
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType) {
                    $valueMatchesType = self::valueMatchesType($value, $type);

                    if (!$valueMatchesType) {
                        $expectedType = $type->getName();

                        if ($type->allowsNull() && $expectedType !== 'null') {
                            $expectedType .= '|null';
                        }

                        return DecodeError::fieldTypeMismatch($class->getName(), $name, $expectedType, $value);
                    }
                }
            }
        }

        return null;
    }

    private static function valueMatchesType(mixed $value, ReflectionNamedType $type): bool
    {
        if ($value === null) {
            return $type->allowsNull();
        }

        return match ($type->getName()) {
            'bool' => is_bool($value),
            'false' => $value === false,
            'float' => is_float($value) || is_int($value),
            'int' => is_int($value),
            'null' => false,
            'string' => is_string($value),
            'true' => $value === true,
            default => true,
        };
    }
}
