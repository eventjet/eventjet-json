<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\ClassGraphValidator;
use Eventjet\Json\Internal\Error\UnsupportedTarget;
use Eventjet\Json\Internal\RootDescriptor;
use stdClass;
use Throwable;

use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;

use const JSON_ERROR_NONE;

final class Json
{
    /**
     * Check declarations, including nested classes, without constructing objects.
     *
     * @param class-string|JsonType<mixed> $type
     */
    public static function validateType(string|JsonType $type): DecodeError|null
    {
        if (!is_string($type)) {
            return RootDescriptor::of($type)->validate();
        }
        try {
            new ClassGraphValidator()->validateRoot($type);
            return null;
        } catch (DecodeError $error) {
            return $error;
        } catch (Throwable $error) {
            return UnsupportedTarget::unreadable($error)->whileCreating($type);
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
        /** @var mixed $values */
        $values = json_decode($json);

        if ($values === null && json_last_error() !== JSON_ERROR_NONE) {
            return DecodeError::invalidJson(json_last_error_msg());
        }

        if (!is_string($class)) {
            return RootDescriptor::of($class)->decode($values);
        }

        if (!$values instanceof stdClass) {
            return DecodeError::unexpectedRootValue($values);
        }

        try {
            return ClassDecoders::hydrate($class, $values);
        } catch (DecodeError $error) {
            return $error;
        }
    }
}
