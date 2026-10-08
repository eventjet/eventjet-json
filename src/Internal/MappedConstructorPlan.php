<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;

/** @internal */
final readonly class MappedConstructorPlan
{
    public bool $cacheable;

    /** @param array<array-key, string> $argumentNames */
    public function __construct(
        private ConstructorPlan $plan,
        private array $argumentNames,
    ) {
        $this->cacheable = $plan->cacheable;
    }

    /** @param array<array-key, array<array-key, mixed>|bool|float|int|object|string|null> $values */
    public function validate(array $values, string $path): DecodeError|null
    {
        return $this->plan->validate($values, $path);
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
        $arguments = [];
        foreach ($converted as $name => $value) {
            $arguments[$this->argumentNames[$name] ?? $name] = $value;
        }
        return $arguments;
    }
}
