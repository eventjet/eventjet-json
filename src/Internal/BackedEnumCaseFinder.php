<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use UnitEnum;

/** @internal */
final class BackedEnumCaseFinder
{
    /**
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    public static function find(string $enumName, mixed $value): UnitEnum|null
    {
        $enum = new ReflectionEnum($enumName);

        foreach ($enum->getCases() as $case) {
            if ($case instanceof ReflectionEnumBackedCase && $case->getBackingValue() === $value) {
                return $case->getValue();
            }
        }

        return null;
    }
}
