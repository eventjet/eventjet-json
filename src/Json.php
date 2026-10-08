<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\DirectJsonParser;
use Eventjet\Json\Internal\DirectListPlan;
use Eventjet\Json\Internal\DirectScalarPlan;
use Eventjet\Json\Internal\NativeJsonDecoder;
use Throwable;

use function is_string;

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
     * @mago-expect lint:halstead Cache transitions preserve cold-request cost and syntax-before-autoload ordering.
     */
    public static function decode(string $json, string|JsonType $class): mixed
    {
        try {
            if (is_string($class) && array_key_exists($class, self::$directPlans)) {
                /**
                 * @psalm-suppress UnnecessaryVarAnnotation Mago needs the conditional generic's object constraint.
                 * @var class-string<T&object> $target The string branch selects a class target.
                 */
                $target = $class;
                $plan = self::$directPlans[$class];
                if ($plan === null) {
                    // Syntax errors must precede declaration resolution and autoloading.
                    if (!json_validate($json)) {
                        return NativeJsonDecoder::decode($json, $target);
                    }
                    /** @mago-expect analysis:less-specific-nested-argument-type The public string branch supplies an object class. */
                    $plan = DirectJsonParser::compile($target);
                    self::$directPlans[$class] = $plan;
                }
                if ($plan !== false) {
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
                return NativeJsonDecoder::decode($json, $target);
            }
            $value = NativeJsonDecoder::decode($json, $class);
            if (is_string($class) && !$value instanceof DecodeError) {
                // Compile only after a successful first decode; cold requests need no second schema.
                self::$directPlans[$class] = null;
            }
            return $value;
        } catch (Throwable $error) {
            /** @mago-expect analysis:less-specific-nested-argument-type Both branches supply the declared class-string target. */
            return DecodeError::cannotInstantiate(is_string($class) ? $class : $class->itemClass(), $error);
        }
    }
}
