<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\JsonType;
use Override;

/**
 * @internal
 * @template T
 * @extends JsonType<ArrayObject<string, T>>
 */
final readonly class MapJsonType extends JsonType
{
    /** @param JsonType<T> $valueType */
    public function __construct(
        private JsonType $valueType,
    ) {}

    #[Override]
    public function node(string $class): MapNode
    {
        return new MapNode($class, $this->valueType->node($class), arrayObject: true);
    }

    /** @return ArrayObject<string, T>|DecodeError */
    #[Override]
    public function decode(mixed $value): ArrayObject|DecodeError
    {
        /** @var ArrayObject<string, T>|DecodeError */
        return $this->node($this->itemClass())->decode($value, '');
    }

    #[Override]
    public function rootShape(): string
    {
        return 'object';
    }

    /** @return class-string */
    #[Override]
    public function itemClass(): string
    {
        return $this->valueType->itemClass();
    }
}
