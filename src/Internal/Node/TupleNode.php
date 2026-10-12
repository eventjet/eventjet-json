<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
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

    /**
     * @param class-string $class
     * @param list<Node> $items
     */
    public function __construct(
        private string $class,
        private array $items,
        private int $required,
    ) {
        $positions = [];
        foreach ($items as $index => $item) {
            $positions[] = $index < $required ? $item : sprintf('%d?: %s', $index, $item);
        }
        $this->expected = 'array{' . implode(', ', $positions) . '}';
    }

    /** @return list<mixed>|DecodeError */
    #[Override]
    public function decode(mixed $value, string $path): array|DecodeError
    {
        if (!is_array($value)) {
            return DecodeError::fieldTypeMismatch($this->class, $path, $this->expected, $value);
        }
        $count = count($value);
        $max = count($this->items);
        if ($count < $this->required || $count > $max) {
            $length = $this->required === $max
                ? sprintf('exactly %d', $max)
                : sprintf('between %d and %d', $this->required, $max);
            return DecodeError::nonInstantiableTarget(
                $this->class,
                sprintf(
                    'Field %s must be of type %s with %s items, %d given.',
                    $path,
                    $this->expected,
                    $length,
                    $count,
                ),
            );
        }
        $decoded = [];
        /** @var list<mixed> $items JSON arrays decode to lists. */
        $items = $value;
        /** @var mixed $item */
        foreach ($items as $index => $item) {
            $node = $this->items[$index] ?? null;
            assert($node !== null, description: 'The length check guarantees a node for every item.');
            $item = $node->decode($item, sprintf('%s[%d]', $path, $index));
            if ($item instanceof DecodeError) {
                return $item;
            }
            $decoded[] = $item;
        }
        return $decoded;
    }

    #[Override]
    public function valueTypes(): array
    {
        return ['array'];
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
