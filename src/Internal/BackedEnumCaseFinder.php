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
    /** @var array<enum-string, self> */
    private static array $finders = [];

    /** @param array<string, UnitEnum> $cases */
    private function __construct(
        private readonly array $cases,
    ) {}

    /**
     * @param enum-string $enumName
     * @throws ReflectionException
     */
    public static function forEnum(string $enumName): self
    {
        return self::$finders[$enumName] ??= new self(self::cases($enumName));
    }

    public function find(mixed $value): UnitEnum|null
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        return $this->cases[get_debug_type($value) . ':' . $value] ?? null;
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
                return [];
            }

            $value = $case->getBackingValue();
            $cases[get_debug_type($value) . ':' . $value] = $case->getValue();
        }

        return $cases;
    }
}
