<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

/** @internal */
final readonly class MappedConstructorPlan
{
    /** @param array<array-key, string> $argumentNames */
    public function __construct(
        private ConstructorPlan $plan,
        private array $argumentNames,
    ) {}

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public function decode(array $values, string $path): array|DecodeError
    {
        $converted = $this->plan->decode($values, $path);
        return $converted instanceof DecodeError ? $converted : $this->arguments($converted);
    }

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>|DecodeError
     * @throws JsonException
     * @throws ReflectionException
     */
    public function convert(array $values, string $path): array|DecodeError
    {
        $converted = $this->plan->convert($values, $path);
        if ($converted instanceof DecodeError) {
            return $converted;
        }
        return $this->arguments($converted);
    }

    /**
     * @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $converted
     * @return array<array-key, array<array-key, mixed>|bool|float|int|object|string|null>
     */
    private function arguments(array $converted): array
    {
        $arguments = [];
        foreach ($converted as $name => $value) {
            $arguments[$this->argumentNames[$name] ?? $name] = $value;
        }
        return $arguments;
    }
}
