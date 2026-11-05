<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidValue;
use JsonException;
use Override;

use function array_is_list;
use function is_array;
use function sprintf;

/** @internal */
final readonly class ListNode implements Node
{
    private string $type;

    public function __construct(
        private Node $item,
        private bool $nonEmpty,
    ) {
        $this->type = sprintf('%s<%s>', $nonEmpty ? 'non-empty-list' : 'list', $item);
    }

    /** @return list<mixed> */
    #[Override]
    public function decode(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw InvalidValue::typeMismatch($this->type, $value);
        }
        if ($this->nonEmpty && $value === []) {
            throw InvalidValue::emptyList($this->type);
        }
        /** @var list<mixed> */
        return $this->item instanceof ScalarNode ? $this->item->decodeEach($value) : $this->decodeItems($value);
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     * @throws DecodeError
     * @throws JsonException
     */
    private function decodeItems(array $values): array
    {
        return $this->item instanceof ObjectNode ? $this->item->decodeEach($values) : $this->decodeEachItem($values);
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     * @throws DecodeError
     * @throws JsonException
     */
    private function decodeEachItem(array $values): array
    {
        $decoded = [];
        /** @var mixed $item */
        foreach ($values as $index => $item) {
            try {
                $decoded[] = $this->item->decode($item);
            } catch (DecodeError $error) {
                throw $error->inItem($index);
            }
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
        return $this->item->dependencies();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
