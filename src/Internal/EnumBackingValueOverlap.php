<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;

use function count;

/** @internal */
final class EnumBackingValueOverlap
{
    /**
     * @param list<enum-string> $enumNames
     * @return array{enum-string, enum-string, int|string}|null
     * @throws ReflectionException
     */
    public static function find(array $enumNames): array|null
    {
        if (count($enumNames) < 2) {
            return null;
        }

        /** @var list<array{enum: enum-string, value: int|string}> $seen */
        $seen = [];

        foreach ($enumNames as $enumName) {
            $enum = new ReflectionEnum($enumName);
            foreach ($enum->getCases() as $case) {
                /** @var ReflectionEnumBackedCase $case */
                $value = $case->getBackingValue();
                foreach ($seen as $existing) {
                    if ($existing['value'] === $value) {
                        return [$existing['enum'], $enumName, $value];
                    }
                }
                $seen[] = ['enum' => $enumName, 'value' => $value];
            }
        }
        return null;
    }
}
