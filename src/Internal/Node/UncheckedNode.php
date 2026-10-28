<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Override;

/**
 * A field whose declared class could not be loaded. Only null can satisfy such a field if it is nullable, so other
 * values report the unknown class instead of failing the constructor call or the assignment. The class remains a
 * dependency, so the decoder is compiled again once the class is loaded.
 *
 * @internal
 */
final readonly class UncheckedNode implements Node
{
    public function __construct(
        public string $name,
        private string $field,
        private bool $nullable,
    ) {}

    #[Override]
    public function decode(mixed $value, string $path): DecodeError|null
    {
        return match (true) {
            $value !== null => UnsupportedFieldClass::unknown($this->field, $this->name),
            $this->nullable => null,
            default => InvalidValue::typeMismatch($path, $this->name, $value),
        };
    }

    #[Override]
    public function valueTypes(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return [$this->name];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
