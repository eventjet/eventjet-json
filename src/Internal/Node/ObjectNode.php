<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\ObjectHydrator;
use Override;
use stdClass;

/** @internal */
final readonly class ObjectNode implements ItemNode
{
    /**
     * @param class-string $class
     * @param class-string $target
     */
    public function __construct(
        private string $class,
        public string $target,
        private bool $nullable,
    ) {}

    #[Override]
    public function decode(mixed $value, string $path): object|null
    {
        if ($value === null && $this->nullable) {
            return null;
        }
        if (!$value instanceof stdClass) {
            $expected = $this->nullable ? FieldTypeNameResolver::withNull($this->target) : $this->target;
            return DecodeError::fieldTypeMismatch($this->class, $path, $expected, $value);
        }
        return ObjectHydrator::hydrate($this->target, $value, $path);
    }

    #[Override]
    public function dependencies(): array
    {
        return [$this->target];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->target;
    }
}
