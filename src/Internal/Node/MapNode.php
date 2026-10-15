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

    /** @return array<string, mixed>|ArrayObject<string, mixed>|DecodeError */
    #[Override]
    public function decode(mixed $value, string $path): array|ArrayObject|DecodeError
    {
        if (!$value instanceof stdClass) {
            return InvalidValue::typeMismatch($path, 'JSON object', $value);
        }
        $values = (array) $value;
        if (!$this->arrayObject && $values === []) {
            return InvalidValue::emptyMap($path);
        }
        foreach (array_keys($values) as $key) {
            if (is_int($key)) {
                return InvalidValue::integerKey($path, $key);
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
        return $decoded instanceof DecodeError || !$this->arrayObject ? $decoded : new ArrayObject($decoded);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>|DecodeError
     * @throws JsonException
     */
    private function decodeValues(array $values, string $path): array|DecodeError
    {
        $decoded = [];
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            $value = $this->value->decode($value, FieldPath::key($path, $key));
            if ($value instanceof DecodeError) {
                return $value;
            }
            $decoded[$key] = $value;
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
