<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\JsonError;

use function count;
use function is_array;

/** @internal */
final readonly class CollectionType
{
    public function __construct(public bool|null $object, public PhpType $item)
    {
    }

    /** @param array<array-key, mixed> $values */
    public static function forEncoding(PhpType|null $type, bool $object, array $values, JsonNode|null $source = null): self|null
    {
        if ($type === null) {
            return null;
        }
        if ($type->name !== 'union') {
            return $type->collection;
        }
        $collections = [];
        foreach ($type->arguments as $option) {
            if ($option->collection !== null) {
                $collections[] = $option->collection;
            }
        }
        if (count($collections) === 1) {
            return $collections[0];
        }
        $matching = [];
        foreach ($collections as $collection) {
            if ($collection->acceptsShape($object) && $collection->acceptsItems($values, $source)) {
                $matching[] = $collection;
            }
        }
        if (count($matching) > 1) {
            throw JsonError::encodeFailed('Ambiguous collection type');
        }
        return $matching[0] ?? null;
    }

    public function acceptsShape(bool $object): bool
    {
        return $this->object === null || $this->object === $object;
    }

    /** @param array<array-key, mixed> $values */
    public function acceptsItems(array $values, JsonNode|null $source = null): bool
    {
        $members = $source !== null && is_array($source->value) ? $source->value : [];
        /** @var mixed $value */
        foreach ($values as $key => $value) {
            if (!$this->item->accepts($value, $members[$key] ?? null)) {
                return false;
            }
        }
        return true;
    }
}
