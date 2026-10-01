<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use UnitEnum;

use function array_key_exists;

/** @internal */
final class ObjectValueConverter
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>|DecodeError
     * @throws ReflectionException
     */
    public static function convert(ReflectionClass $class, array $values): array|DecodeError
    {
        $className = $class->getName();

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $parameter->getName();
            $converted = self::convertField($className, $parameter, $values);

            if ($converted instanceof DecodeError) {
                return $converted;
            }

            if ($converted instanceof BackedEnum) {
                $values[$field] = $converted;
            }
        }

        return $values;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed> $values
     * @throws ReflectionException
     */
    private static function convertField(
        string $class,
        ReflectionParameter $parameter,
        array $values,
    ): UnitEnum|DecodeError|null {
        $field = $parameter->getName();

        if (!array_key_exists($field, $values)) {
            return null;
        }

        /** @var mixed $value */
        $value = $values[$field];

        return BackedEnumValueConverter::convert($class, $parameter, $value);
    }
}
