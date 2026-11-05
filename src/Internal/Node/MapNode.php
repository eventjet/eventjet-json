<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use JsonException;
use Override;
use stdClass;

use function array_keys;
use function is_int;
use function sprintf;

/**
 * A `non-empty-array<string, T>`, `ArrayObject<string, T>`, or `ArrayObject<array-key, T>` map.
 *
 * @internal
 */
final readonly class MapNode implements Node
{
    private function __construct(
        private Node $value,
        private bool $arrayObject,
        private bool $integerKeys,
    ) {}

    public static function nonEmptyArray(Node $value): self
    {
        return new self($value, arrayObject: false, integerKeys: false);
    }

    public static function stringKeyArrayObject(Node $value): self
    {
        return new self($value, arrayObject: true, integerKeys: false);
    }

    public static function anyKeyArrayObject(Node $value): self
    {
        return new self($value, arrayObject: true, integerKeys: true);
    }

    /** @return array<array-key, mixed>|ArrayObject<array-key, mixed> */
    #[Override]
    public function decode(mixed $value): array|ArrayObject
    {
        if (!$value instanceof stdClass) {
            throw InvalidValue::typeMismatch('JSON object', $value);
        }
        $values = (array) $value;
        if (!$this->arrayObject && $values === []) {
            throw InvalidValue::emptyMap();
        }
        if (!$this->integerKeys) {
            foreach (array_keys($values) as $key) {
                if (is_int($key)) {
                    throw InvalidValue::integerKey($key);
                }
            }
        }
        $decoded = $this->value instanceof ScalarNode
            ? $this->value->decodeEach($values)
            : $this->decodeValues($values);
        return $this->arrayObject ? new ArrayObject($decoded) : $decoded;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     * @throws DecodeError
     * @throws JsonException
     */
    private function decodeValues(array $values): array
    {
        $decoded = [];
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            try {
                $decoded[$key] = $this->value->decode($value);
            } catch (DecodeError $error) {
                throw $error->inItem($key);
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
        return $this->value->dependencies();
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            '%s<%s, %s>',
            $this->arrayObject ? 'ArrayObject' : 'non-empty-array',
            $this->integerKeys ? 'array-key' : 'string',
            $this->value,
        );
    }
}
