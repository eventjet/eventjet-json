<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\Internal\Error\InvalidValue;
use Override;

use function assert;
use function count;
use function implode;
use function is_array;
use function sprintf;

/** @internal */
final readonly class TupleNode implements Node
{
    private string $expected;

    /** @param list<Node> $items */
    public function __construct(
        private array $items,
        private int $required,
    ) {
        $positions = [];
        foreach ($items as $index => $item) {
            $positions[] = $index < $required ? $item : sprintf('%d?: %s', $index, $item);
        }
        $this->expected = 'array{' . implode(', ', $positions) . '}';
    }

    /** @return list<mixed> */
    #[Override]
    public function decode(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw InvalidValue::typeMismatch($path, $this->expected, $value);
        }
        $count = count($value);
        $max = count($this->items);
        if ($count < $this->required || $count > $max) {
            throw InvalidValue::tupleLength($path, $this->expected, $this->required, $max, $count);
        }
        $decoded = [];
        /** @var list<mixed> $items JSON arrays decode to lists. */
        $items = $value;
        /** @var mixed $item */
        foreach ($items as $index => $item) {
            $node = $this->items[$index] ?? null;
            assert($node !== null, description: 'The length check guarantees a node for every item.');
            $decoded[] = $node->decode($item, sprintf('%s[%d]', $path, $index));
        }
        return $decoded;
    }

    #[Override]
    public function claims(): array
    {
        return ['array' => ['others' => 'accepts', 'enums' => [], 'values' => []]];
    }

    #[Override]
    public function dependencies(): array
    {
        $dependencies = [];
        foreach ($this->items as $item) {
            $dependencies = [...$dependencies, ...$item->dependencies()];
        }
        return $dependencies;
    }

    #[Override]
    public function __toString(): string
    {
        $entries = [];
        foreach ($this->items as $index => $item) {
            $entries[] = sprintf('%d%s: %s', $index, $index >= $this->required ? '?' : '', $item);
        }
        return 'array{' . implode(', ', $entries) . '}';
    }
}
