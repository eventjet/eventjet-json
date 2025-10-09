<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;
use Stringable;

use function array_key_last;
use function array_slice;
use function array_values;
use function implode;
use function json_encode;
use function ksort;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

final class Union extends JsonType
{
    /** @var non-empty-list<JsonType> */
    public readonly array $types;

    /**
     * @no-named-arguments
     * @internal Use {@see JsonType::union()} or the instance method {@see JsonType::or()} instead.
     */
    public function __construct(JsonType $first, JsonType $second, JsonType ...$other)
    {
        $this->types = [$first, $second, ...$other];
    }

    /**
     * @param non-empty-array<array-key, string | Stringable> $parts
     */
    private static function disjunction(array $parts): string
    {
        $commaSeparated = implode(', ', array_slice($parts, 0, -1));
        $last = $parts[array_key_last($parts)];
        return sprintf('%s or %s', $commaSeparated, $last);
    }

    public function __toString(): string
    {
        return implode(' | ', $this->types);
    }

    #[Override]
    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        foreach ($this->types as $type) {
            $result = $type->validateValue($value, $path);
            if (!$result->isValid()) {
                continue;
            }
            return $result;
        }
        $expected = self::disjunction($this->canonicalize()->types);
        return ValidationResult::error(
            sprintf('Expected %s, got %s.', $expected, json_encode($value, JSON_THROW_ON_ERROR)),
            $path,
        );
    }

    #[Override]
    public function canonicalize(): static
    {
        $types = [];
        foreach ($this->types as $type) {
            $type = $type->canonicalize();
            $types[(string)$type] = $type;
        }
        ksort($types, SORT_STRING);
        return new self(...array_values($types));
    }
}
