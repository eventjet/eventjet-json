<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use stdClass;
use Throwable;

use function get_object_vars;

/** @internal */
final class ObjectHydrator
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|DecodeError
     */
    public static function hydrate(string $class, stdClass $object): object
    {
        try {
            $reflection = new ReflectionClass($class);
            $values = get_object_vars($object);
            $typeError = ObjectTypeValidator::validate($reflection, $values);

            if ($typeError !== null) {
                return $typeError;
            }

            $convertedValues = ObjectValueConverter::convert($reflection, $values);

            if ($convertedValues instanceof DecodeError) {
                return $convertedValues;
            }

            /**
             * @mago-expect analysis:unknown-class-instantiation The constructor target is intentionally dynamic.
             * @psalm-suppress MixedMethodCall PHP validates the intentionally dynamic constructor at runtime.
             */
            return new $class(...$convertedValues);
        } catch (Throwable $error) {
            return DecodeError::cannotInstantiate($class, $error);
        }
    }
}
