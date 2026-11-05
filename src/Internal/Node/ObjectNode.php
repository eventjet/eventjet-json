<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ClassDecoders;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use JsonException;
use Override;
use stdClass;

/** @internal */
final readonly class ObjectNode implements Node
{
    /** @param class-string $target */
    public function __construct(
        public string $target,
        private bool $nullable,
    ) {}

    #[Override]
    public function decode(mixed $value): object|null
    {
        if ($value === null && $this->nullable) {
            return null;
        }
        if (!$value instanceof stdClass) {
            $expected = $this->nullable ? FieldTypeNameResolver::withNull($this->target) : $this->target;
            throw InvalidValue::typeMismatch($expected, $value);
        }
        return ClassDecoders::hydrate($this->target, $value);
    }

    /**
     * Decodes the items of a list without a node call per item, because lists of objects are common.
     *
     * @param list<mixed> $values
     * @return list<object|null>
     * @throws DecodeError
     * @throws JsonException
     */
    public function decodeEach(array $values): array
    {
        $decoded = [];
        /** @var mixed $value */
        foreach ($values as $index => $value) {
            try {
                $decoded[] = $value instanceof stdClass
                    ? ClassDecoders::hydrate($this->target, $value)
                    : $this->decode($value);
            } catch (DecodeError $error) {
                throw $error->inItem($index);
            }
        }
        return $decoded;
    }

    #[Override]
    public function claims(): array
    {
        return ['stdClass' => ['others' => 'accepts', 'enums' => [], 'values' => []]];
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
