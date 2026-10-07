<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use UnitEnum;

use function get_debug_type;
use function is_int;
use function is_string;

/** @internal */
final class BackedEnumCaseFinder
{
    /** @var array<enum-string, array<string, UnitEnum>> */
    private static array $cases = [];

    /**
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    public static function find(string $enumName, mixed $value): UnitEnum|null
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        self::$cases[$enumName] ??= self::cases($enumName);
        $cases = self::$cases[$enumName];

        return $cases[get_debug_type($value) . ':' . $value] ?? null;
    }

    /**
     * @param enum-string $enumName
     * @return array<string, UnitEnum>
     * @throws ReflectionException
     */
    private static function cases(string $enumName): array
    {
        $cases = [];

        foreach (new ReflectionEnum($enumName)->getCases() as $case) {
            if (!$case instanceof ReflectionEnumBackedCase) {
                continue;
            }

            $value = $case->getBackingValue();
            $cases[get_debug_type($value) . ':' . $value] = $case->getValue();
        }

        return $cases;
    }
}
