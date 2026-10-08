<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ClassGraphValidator;
use Throwable;

use function is_string;

final class Json
{
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
     * @param string $json
     * @param class-string<T&object>|JsonType<T> $class
     * @phpstan-param (T is object ? class-string<T> : never)|JsonType<T> $class
     * @psalm-param class-string<T&object>|JsonType<T> $class
     * @return T|DecodeError
     */
    public static function decode(string $json, string|JsonType $class): mixed
    {
        try {
            /**
             * @mago-expect lint:inline-variable-return The local annotation preserves the generic public return contract.
             * @var T|DecodeError $value The selected construction plan preserves the requested target type.
             * @mago-expect analysis:less-specific-nested-argument-type JsonType is covariant; decoding does not mutate the descriptor.
             */
            $value = \Eventjet\Json\Internal\DirectJsonParser::decode($json, $class);
            return $value;
        } catch (Throwable $error) {
            /** @mago-expect analysis:less-specific-nested-argument-type Both branches supply the declared class-string target. */
            return DecodeError::cannotInstantiate(is_string($class) ? $class : $class->itemClass(), $error);
        }
    }
}
