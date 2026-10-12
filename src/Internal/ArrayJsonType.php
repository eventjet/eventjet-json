<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\JsonType;
use Override;

/**
 * @internal
 * @template-covariant T
 * @extends JsonType<list<T>>
 */
final readonly class ArrayJsonType extends JsonType
{
    /** @param JsonType<T> $itemType */
    public function __construct(
        private JsonType $itemType,
    ) {}

    #[Override]
    public function node(string $class): ListNode
    {
        return new ListNode($class, $this->itemType->node($class), nonEmpty: false);
    }

    /** @return list<T>|DecodeError */
    #[Override]
    public function decode(mixed $value): array|DecodeError
    {
        return $this->node($this->itemClass())->decode($value, '');
    }

    #[Override]
    public function rootShape(): string
    {
        return 'array';
    }

    /** @return class-string */
    #[Override]
    public function itemClass(): string
    {
        return $this->itemType->itemClass();
    }
}
