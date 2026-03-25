<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use function array_is_list;
use function is_array;
use function json_encode;
use function sprintf;

final class Array_ extends JsonType
{
    /**
     * @internal Use {@see JsonType::array()} instead.
     */
    public function __construct(public readonly JsonType $elementType)
    {
    }

    public function __toString(): string
    {
        return sprintf('Array<%s>', $this->elementType);
    }

    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if (!is_array($value)) {
            return ValidationResult::error(sprintf('Expected array, got %s.', (string)json_encode($value)), $path);
        }
        if (!array_is_list($value)) {
            return ValidationResult::error('Expected array, got object.', $path);
        }
        $results = [];
        /** @var mixed $element */
        foreach ($value as $key => $element) {
            $results[] = $this->elementType->validateValue($element, self::joinPath($path, $key));
        }
        if ($results === []) {
            return ValidationResult::valid();
        }
        return ValidationResult::merge($results);
    }

    public function canonicalize(): JsonType
    {
        return new self($this->elementType->canonicalize());
    }
}
