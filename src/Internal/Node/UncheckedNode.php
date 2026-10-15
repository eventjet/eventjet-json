<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
use Override;

/**
 * A constructor parameter whose declared class could not be loaded. Only null can satisfy such a parameter, so other
 * values report the unknown class instead of failing the constructor call. The class remains a dependency, so the
 * decoder is compiled again once the class is loaded.
 *
 * @internal
 */
final readonly class UncheckedNode implements Node
{
    public function __construct(
        public string $name,
        private string $field,
    ) {}

    #[Override]
    public function decode(mixed $value, string $path): DecodeError|null
    {
        return $value === null ? null : UnsupportedFieldClass::unknown($this->field, $this->name);
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
