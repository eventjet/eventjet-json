<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\DirectJsonParser;
use Eventjet\Json\Internal\DirectListPlan;
use Eventjet\Json\Internal\DirectScalarPlan;
use Eventjet\Json\Internal\MapType;
use Eventjet\Json\Internal\NestedCollectionType;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\RootTypeValidator;
use ReflectionClass;
use stdClass;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;

use const JSON_ERROR_NONE;

/** @mago-expect lint:cyclomatic-complexity The public API validates root shapes and dispatches cached direct plans. */
final class Json
{
    /** @var array<class-string, DirectScalarPlan|DirectListPlan|false|null> */
    private static array $directPlans = [];

    /**
     * Check declarations, including nested classes, without constructing objects.
     *
     * @param class-string|JsonType<mixed> $type
     */
    public static function validateType(string|JsonType $type): DecodeError|null
    {
        $class = is_string($type) ? $type : $type->itemClass();
        try {
            if (is_string($type)) {
                return new ClassGraphValidator()->validateRoot($class);
            }
            return (
                ClassFieldTypeValidator::validate($class, '[]', $class) ?? new ClassGraphValidator()->validate($class)
            );
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @template T
     * @param class-string<T&object>|JsonType<T> $class
     * @phpstan-param (T is object ? class-string<T> : never)|JsonType<T> $class
     * @psalm-param class-string<T&object>|JsonType<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $json, string|JsonType $class): mixed
    {
        $warm = is_string($class) && array_key_exists($class, self::$directPlans);
        if ($warm) {
            $target = $class;
            try {
                $plan = self::$directPlans[$target] ?? null;
                // Syntax errors must precede declaration resolution and autoloading.
                if ($plan === null && json_validate($json)) {
                    $plan = DirectJsonParser::compile($target);
                    self::$directPlans[$target] = $plan;
                }
                if ($plan !== null && $plan !== false) {
                    $direct = $plan->decode($json);
                    if ($direct !== false) {
                        /**
                         * @var T&object $value The cached plan's class key is the requested target.
                         * @mago-expect lint:inline-variable-return The annotation preserves the public generic contract.
                         */
                        $value = $direct;
                        return $value;
                    }
                }
            } catch (Throwable $error) {
                return DecodeError::cannotInstantiate($target, $error);
            }
        }
        /** @var mixed $values */
        $values = json_decode($json);
        if ($values === null && json_last_error() !== JSON_ERROR_NONE) {
            return DecodeError::invalidJson(json_last_error_msg());
        }
        if (!is_string($class)) {
            return self::decodeCollection($class, $values);
        }
        if (!$values instanceof stdClass) {
            return DecodeError::unexpectedRootValue($values);
        }
        $value = ObjectHydrator::hydrate($class, $values);
        if (!$warm && !$value instanceof DecodeError) {
            // Compile only after a successful first decode; cold requests need no second schema.
            self::$directPlans[$class] = null;
        }
        return $value;
    }

    /**
     * @template T
     * @param JsonType<T> $type
     * @return T|DecodeError
     */
    private static function decodeCollection(JsonType $type, mixed $values): mixed
    {
        $rootType = self::rootType($type);
        $matchesRoot = match ($rootType) {
            'object' => $values instanceof stdClass,
            'array' => is_array($values),
        };
        if (!$matchesRoot) {
            return DecodeError::unexpectedRootValue($values, $rootType);
        }
        $class = $type->itemClass();
        try {
            $error = ClassFieldTypeValidator::validate($class, '[]', $class) ?? RootTypeValidator::validate(
                new ReflectionClass($class),
            );
            if ($error !== null) {
                return $error;
            }
            return $type->decodeValue($values);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }

    /**
     * @param JsonType<mixed> $type
     * @return 'object'|'array'
     */
    private static function rootType(JsonType $type): string
    {
        $collection = $type->collectionItem();
        return $collection instanceof NestedCollectionType && $collection->collection instanceof MapType
            ? 'object'
            : 'array';
    }
}
