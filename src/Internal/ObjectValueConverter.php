<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use UnitEnum;

use function array_key_exists;
use function enum_exists;
use function get_debug_type;
use function sprintf;
use function var_export;

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
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || !array_key_exists($field, $values)) {
            return null;
        }

        $typeName = $type->getName();

        if (!enum_exists($typeName)) {
            return null;
        }

        /** @var mixed $value */
        $value = $values[$field];

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        $enum = new ReflectionEnum($typeName);
        /** @var ReflectionNamedType $backingType */
        $backingType = $enum->getBackingType();
        $valueMatchesBackingType = ValueTypeMatcher::matches($value, $backingType);

        if (!$valueMatchesBackingType) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum',
                $typeName,
                sprintf(
                    'backed enum %s, which expects a %s backing value; %s given.',
                    $typeName,
                    $backingType->getName(),
                    get_debug_type($value),
                ),
            );
        }

        foreach ($enum->getCases() as $case) {
            if ($case instanceof ReflectionEnumBackedCase && $case->getBackingValue() === $value) {
                return $case->getValue();
            }
        }

        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'backed enum',
            $typeName,
            sprintf(
                'backed enum %s, which has no case with backing value %s.',
                $typeName,
                var_export($value, return: true),
            ),
        );
    }
}
