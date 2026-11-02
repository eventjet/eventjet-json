<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\FieldPath;
use JsonException;
use Override;
use stdClass;

use function array_keys;
use function is_int;
use function sprintf;

/** @internal */
final readonly class MapNode implements Node
{
    public function __construct(
        private Node $value,
        public bool $arrayObject,
        private bool $anyKey = false,
    ) {}

    /** @return array<array-key, mixed>|ArrayObject<array-key, mixed> */
    #[Override]
    public function decode(mixed $value, string $path): array|ArrayObject
    {
        if (!$value instanceof stdClass) {
            throw InvalidValue::typeMismatch($path, 'JSON object', $value);
        }
        $values = (array) $value;
        if (!$this->arrayObject && $values === []) {
            throw InvalidValue::emptyMap($path);
        }
        if (!$this->anyKey) {
            foreach (array_keys($values) as $key) {
                if (is_int($key)) {
                    throw InvalidValue::integerKey($path, $key);
                }
            }
        }
        $decoded = $this->value instanceof ScalarNode
            ? $this->value->decodeEach(
                $values,
                /** @throws JsonException */
                static fn(int|string $key): string => FieldPath::key($path, (string) $key),
            )
            : $this->decodeValues($values, $path);
        return $this->arrayObject ? new ArrayObject($decoded) : $decoded;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     * @throws DecodeError
     * @throws JsonException
     */
    private function decodeValues(array $values, string $path): array
    {
        $decoded = [];
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            $decoded[$key] = $this->value->decode($value, FieldPath::key($path, (string) $key));
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
            $this->anyKey ? 'array-key' : 'string',
            $this->value,
        );
    }
}
