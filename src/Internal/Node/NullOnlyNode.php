<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Override;

/**
 * A nullable declaration whose non-null alternative has no JSON representation, such as a non-backed enum.
 *
 * @internal
 */
final readonly class NullOnlyNode implements Node
{
    /** @param class-string $class */
    public function __construct(
        private string $class,
        private string $type,
    ) {}

    #[Override]
    public function decode(mixed $value, string $path): DecodeError|null
    {
        return $value === null ? null : DecodeError::fieldTypeMismatch($this->class, $path, $this->type, $value);
    }

    #[Override]
    public function valueTypes(): array
    {
        return ['null'];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
