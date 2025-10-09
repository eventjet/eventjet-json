<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;

use function array_is_list;
use function array_key_exists;
use function implode;
use function is_array;
use function json_encode;
use function ksort;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class Object_ extends JsonType
{
    /**
     * @param array<string, Member> $members
     * @internal Use {@see JsonType::object()} instead.
     */
    public function __construct(public readonly array $members)
    {
    }

    public function __toString(): string
    {
        $members = [];
        foreach ($this->members as $name => $member) {
            $members[] = sprintf('%s%s: %s', $name, $member->required ? '' : '?', $member->type);
        }
        return sprintf('{%s}', implode(', ', $members));
    }

    #[Override]
    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if (!is_array($value)) {
            return ValidationResult::error(
                sprintf('Expected object, got %s.', json_encode($value, JSON_THROW_ON_ERROR)),
                $path,
            );
        }
        if ($value !== [] && array_is_list($value)) {
            return ValidationResult::error(
                sprintf('Expected object, got %s.', json_encode($value, JSON_THROW_ON_ERROR)),
                $path,
            );
        }
        $results = [];
        foreach ($this->members as $name => $member) {
            if (array_key_exists($name, $value)) {
                $results[] = $member->type->validateValue($value[$name], self::joinPath($path, $name));
                continue;
            }
            if (!$member->required) {
                continue;
            }
            $results[] = ValidationResult::error('Missing required member.', self::joinPath($path, $name));
        }
        return ValidationResult::merge($results);
    }

    #[Override]
    public function canonicalize(): JsonType
    {
        $members = [];
        foreach ($this->members as $name => $member) {
            $members[$name] = $member->withCanonicalizedType();
        }
        ksort($members);
        return new self($members);
    }
}
