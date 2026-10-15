<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Override;
use stdClass;

use function sprintf;

/** @internal */
final readonly class ObjectNode implements Node
{
    /** @param class-string $target */
    public function __construct(
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
            return InvalidValue::typeMismatch($path, $expected, $value);
        }
        return ClassDecoders::hydrate($this->target, $value, $path);
    }

    /**
     * Decodes the items of a list without a node call per item, because lists of objects are common.
     *
     * @param list<mixed> $values
     * @return list<object>|DecodeError
     */
    public function decodeEach(array $values, string $path): array|DecodeError
    {
        $decoded = [];
        /** @var mixed $value */
        foreach ($values as $index => $value) {
            $itemPath = sprintf('%s[%d]', $path, $index);
            if (!$value instanceof stdClass) {
                return InvalidValue::typeMismatch($itemPath, $this->target, $value);
            }
            $object = ClassDecoders::hydrate($this->target, $value, $itemPath);
            if ($object instanceof DecodeError) {
                return $object;
            }
            $decoded[] = $object;
        }
        return $decoded;
    }

    #[Override]
    public function valueTypes(): array
    {
        return ['stdClass'];
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
