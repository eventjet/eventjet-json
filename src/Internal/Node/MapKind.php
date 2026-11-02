<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use ArrayObject;
use Eventjet\Json\Internal\MapKeys;
use Eventjet\Json\Internal\PhpDocType;

use function sprintf;

/**
 * The supported map declarations. `non-empty-array` maps keep string keys, because `json_encode()` writes an array with
 * integer keys as a list.
 *
 * @internal
 */
enum MapKind
{
    case NonEmptyArray;
    case StringKeyArrayObject;
    case AnyKeyArrayObject;

    /**
     * Returns the kind and the value type of a `non-empty-array`, `non-empty-map`, or `ArrayObject` type with resolved
     * names, or null if its arguments declare no supported map.
     *
     * @return array{self, PhpDocType}|null
     */
    public static function entry(PhpDocType $type): array|null
    {
        $entry = $type->mapEntry();
        if ($entry === null) {
            return null;
        }
        [$keys, $value] = $entry;
        $kind = match (true) {
            $type->name !== ArrayObject::class => $keys === MapKeys::String ? self::NonEmptyArray : null,
            $keys === MapKeys::String => self::StringKeyArrayObject,
            default => self::AnyKeyArrayObject,
        };
        return $kind === null ? null : [$kind, $value];
    }

    /** @mutation-free */
    public function isArrayObject(): bool
    {
        return $this !== self::NonEmptyArray;
    }

    /** @mutation-free */
    public function acceptsIntegerKeys(): bool
    {
        return $this === self::AnyKeyArrayObject;
    }

    public function type(string $value): string
    {
        return sprintf(
            '%s<%s, %s>',
            $this->isArrayObject() ? 'ArrayObject' : 'non-empty-array',
            $this->acceptsIntegerKeys() ? 'array-key' : 'string',
            $value,
        );
    }
}
