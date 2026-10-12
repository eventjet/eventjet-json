<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use JsonException;
use Override;

use function array_is_list;
use function is_array;
use function sprintf;

/** @internal */
final readonly class ListNode implements ItemNode
{
    private string $type;

    /** @param class-string $class */
    public function __construct(
        private string $class,
        private ItemNode $item,
        private bool $nonEmpty,
    ) {
        $this->type = sprintf('%s<%s>', $nonEmpty ? 'non-empty-list' : 'list', $item);
    }

    /** @return list<mixed>|DecodeError */
    #[Override]
    public function decode(mixed $value, string $path): array|DecodeError
    {
        if (!is_array($value) || !array_is_list($value)) {
            return DecodeError::fieldTypeMismatch($this->class, $path, $this->type, $value);
        }
        if ($this->nonEmpty && $value === []) {
            return DecodeError::nonInstantiableTarget(
                $this->class,
                sprintf('Field %s must be of type %s, empty list given.', $path, $this->type),
            );
        }
        /** @var list<mixed>|DecodeError */
        return $this->item instanceof ScalarNode
            ? $this->item->decodeEach($value, static fn(int $index): string => sprintf('%s[%d]', $path, $index))
            : $this->decodeItems($value, $path);
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>|DecodeError
     * @throws JsonException
     */
    private function decodeItems(array $values, string $path): array|DecodeError
    {
        return $this->item instanceof ObjectNode
            ? $this->item->decodeEach($values, $path)
            : $this->decodeEachItem($values, $path);
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>|DecodeError
     * @throws JsonException
     */
    private function decodeEachItem(array $values, string $path): array|DecodeError
    {
        $decoded = [];
        /** @var mixed $item */
        foreach ($values as $index => $item) {
            $item = $this->item->decode($item, sprintf('%s[%d]', $path, $index));
            if ($item instanceof DecodeError) {
                return $item;
            }
            $decoded[] = $item;
        }
        return $decoded;
    }

    #[Override]
    public function dependencies(): array
    {
        return $this->item->dependencies();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
