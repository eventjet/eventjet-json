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
    /** @var array<class-string, DirectScalarPlan|DirectListPlan|bool> */
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
     * @mago-expect lint:halstead Inline compatibility decoding avoids a forwarding call on small workloads.
     */
    public static function decode(string $json, string|JsonType $class): mixed
    {
        $plan = is_string($class) ? self::$directPlans[$class] ?? null : null;
        if ($plan !== false && $plan !== null) {
            /**
             * @var class-string $target Cached plans and pending markers belong only to class targets.
             * @phpstan-var class-string<T&object> $target
             * @psalm-var class-string<T&object> $target
             */
            $target = $class;
            try {
                // Syntax errors must precede declaration resolution and autoloading.
                if ($plan === true && json_validate($json)) {
                    /** @mago-expect analysis:less-specific-nested-argument-type The pending cache key is a class target. */
                    $plan = DirectJsonParser::compile($target);
                    self::$directPlans[$target] = $plan;
                }
                if ($plan !== true && $plan !== false) {
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
                /** @mago-expect analysis:less-specific-nested-argument-type The active cache key is a class target. */
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
        if ($plan !== null) {
            return ObjectHydrator::hydrate($class, $values);
        }
        $value = ObjectHydrator::hydrate($class, $values);
        if (!$value instanceof DecodeError) {
            // Compile only after a successful first decode; cold requests need no second schema.
            self::$directPlans[$class] = true;
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
