<?php

declare(strict_types=1);

namespace Eventjet\Json\Type;

use Override;

use function array_is_list;
use function is_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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

    #[Override]
    public function validateValue(mixed $value, string $path = ''): ValidationResult
    {
        if (!is_array($value)) {
            return ValidationResult::error(
                sprintf('Expected array, got %s.', json_encode($value, JSON_THROW_ON_ERROR)),
                $path,
            );
        }
        if (!array_is_list($value)) {
            return ValidationResult::error('Expected array, got object.', $path);
        }
        $results = [];
        /** @var mixed $element */
        foreach ($value as $key => $element) {
            $results[] = $this->elementType->validateValue($element, self::joinPath($path, $key));
        }
        return ValidationResult::merge($results);
    }

    #[Override]
    public function canonicalize(): JsonType
    {
        return new self($this->elementType->canonicalize());
    }
}
