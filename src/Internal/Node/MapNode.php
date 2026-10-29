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
    ) {}

    /** @return array<string, mixed>|ArrayObject<string, mixed> */
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
        foreach (array_keys($values) as $key) {
            if (is_int($key)) {
                throw InvalidValue::integerKey($path, $key);
            }
        }
        /** @var array<string, mixed> $values */
        $decoded = $this->value instanceof ScalarNode
            ? $this->value->decodeEach(
                $values,
                /** @throws JsonException */
                static fn(string $key): string => FieldPath::key($path, $key),
            )
            : $this->decodeValues($values, $path);
        return $this->arrayObject ? new ArrayObject($decoded) : $decoded;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     * @throws DecodeError
     * @throws JsonException
     */
    private function decodeValues(array $values, string $path): array
    {
        $decoded = [];
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            $decoded[$key] = $this->value->decode($value, FieldPath::key($path, $key));
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
        return $this->value->dependencies();
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s<string, %s>', $this->arrayObject ? 'ArrayObject' : 'non-empty-array', $this->value);
    }
}
