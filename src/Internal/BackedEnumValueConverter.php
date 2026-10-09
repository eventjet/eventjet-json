<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Closure;
use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use UnitEnum;

use function count;
use function enum_exists;
use function get_debug_type;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function sort;
use function sprintf;
use function var_export;

/** @internal */
final class BackedEnumValueConverter
{
    /** @var array<enum-string, array{type: 'int'|'string'|'', find: (Closure(int|string): (BackedEnum|null))|null}> */
    private static array $plans = [];

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function convert(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        mixed $value,
        string $path,
    ): UnitEnum|DecodeError|null {
        $type = EnumFieldTypes::resolve($field);

        if (is_array($type)) {
            return self::convertUnion($class, $path, $type, $value);
        }

        return null;
    }

    /**
     * @param class-string $class
     * @param array<array-key, string> $enumNames
     * @throws ReflectionException
     */
    public static function convertUnion(
        string $class,
        string $field,
        array $enumNames,
        mixed $value,
    ): UnitEnum|DecodeError|null {
        /** @var list<enum-string> $matchingBackingEnums */
        $matchingBackingEnums = [];

        foreach ($enumNames as $enumName) {
            if (!enum_exists($enumName)) {
                continue;
            }
            $plan = self::plan($enumName);
            $find = $plan['find'];
            if ($find === null) {
                continue;
            }
            $valueMatchesBackingType = get_debug_type($value) === $plan['type'];

            if ($valueMatchesBackingType) {
                $matchingBackingEnums[] = $enumName;
                /** @var int|string $value */
                $case = $find($value);

                if ($case !== null) {
                    return $case;
                }
            }
        }

        if ($matchingBackingEnums !== []) {
            return self::unknownUnionValue($class, $field, $matchingBackingEnums, $value);
        }
        return null;
    }

    /**
     * @param class-string $class
     * @param non-empty-list<enum-string> $matchingBackingEnums
     */
    public static function unknownUnionValue(
        string $class,
        string $field,
        array $matchingBackingEnums,
        mixed $value,
    ): DecodeError {
        sort($matchingBackingEnums);

        if (count($matchingBackingEnums) === 1) {
            return self::unknownValue($class, $field, $matchingBackingEnums[0], $value);
        }

        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'backed enum union',
            implode('|', $matchingBackingEnums),
            sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
        );
    }

    /**
     * @param class-string $class
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    public static function convertValue(
        string $class,
        string $field,
        string $enumName,
        mixed $value,
        ReflectionNamedType|null $declaredType = null,
    ): UnitEnum|DecodeError {
        $plan = self::plan($enumName);
        $find = $plan['find'];
        if ($find === null) {
            return DecodeError::fieldTypeMismatch($class, $field, (string) ($declaredType ?? $enumName), $value);
        }
        $backingType = $plan['type'];
        $valueMatchesBackingType = $backingType === 'int' ? is_int($value) : is_string($value);

        if (!$valueMatchesBackingType) {
            return DecodeError::nonInstantiableField(
                $class,
                $field,
                'backed enum',
                $enumName,
                sprintf(', which expects a %s backing value; %s given.', $backingType, get_debug_type($value)),
            );
        }

        /** @var int|string $value */
        $case = $find($value);

        if ($case !== null) {
            return $case;
        }

        return self::unknownValue($class, $field, $enumName, $value);
    }

    /**
     * @param enum-string $enumName
     * @return array{type: 'int'|'string'|'', find: (Closure(int|string): (BackedEnum|null))|null}
     * @throws ReflectionException
     */
    private static function plan(string $enumName): array
    {
        return self::$plans[$enumName] ??= self::createPlan($enumName);
    }

    /**
     * @param enum-string $enumName
     * @return array{type: 'int'|'string'|'', find: (Closure(int|string): (BackedEnum|null))|null}
     * @throws ReflectionException
     */
    private static function createPlan(string $enumName): array
    {
        $backingType = (string) new ReflectionEnum($enumName)->getBackingType();
        $type = match ($backingType) {
            'int' => 'int',
            'string' => 'string',
            default => '',
        };
        if ($type === '') {
            return ['type' => '', 'find' => null];
        }
        /** @var callable(int|string): (BackedEnum|null) $callback */
        $callback = [$enumName, 'tryFrom'];
        /** @mago-var Closure(int|string): (BackedEnum|null) $find */
        $find = Closure::fromCallable($callback);
        return ['type' => $type, 'find' => $find];
    }

    /**
     * @param class-string $class
     * @param enum-string $enumName
     */
    private static function unknownValue(string $class, string $field, string $enumName, mixed $value): DecodeError
    {
        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'backed enum',
            $enumName,
            sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
        );
    }
}
