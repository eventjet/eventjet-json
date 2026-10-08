<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;

use function enum_exists;
use function json_encode;
use function ltrim;

/** @internal */
final class PhpDocLiteralEnumOverlap
{
    /** @throws ReflectionException */
    public static function matches(string|int|float|bool|BackedEnum $literal, string $other): bool
    {
        if (!enum_exists($other)) {
            return false;
        }
        if ($literal instanceof BackedEnum && $literal::class === ltrim($other, characters: '\\')) {
            return false;
        }
        $value = $literal instanceof BackedEnum ? $literal->value : $literal;
        foreach (new ReflectionEnum($other)->getCases() as $case) {
            $backingValue = $case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : null;
            $encodedCase = json_encode($backingValue);
            $encodedValue = json_encode($value);
            if ($case instanceof ReflectionEnumBackedCase && $encodedCase === $encodedValue) {
                return true;
            }
        }
        return false;
    }
}
