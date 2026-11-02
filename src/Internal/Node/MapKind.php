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
        $pair = $type->argumentPair();
        if ($pair === null) {
            return null;
        }
        $keys = MapKeys::of($pair[0]);
        $kind = $keys === null ? null : self::of($type->name, $keys);
        return $kind === null ? null : [$kind, $pair[1]];
    }

    /**
     * Returns the kind of a `non-empty-array`, `non-empty-map`, or `ArrayObject` container with the given keys, or null if
     * the container does not accept them.
     *
     * @mutation-free
     */
    public static function of(string $container, MapKeys $keys): self|null
    {
        return match (true) {
            $container !== ArrayObject::class => $keys === MapKeys::String ? self::NonEmptyArray : null,
            $keys === MapKeys::String => self::StringKeyArrayObject,
            default => self::AnyKeyArrayObject,
        };
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
        return sprintf(match ($this) {
            self::NonEmptyArray => 'non-empty-array<string, %s>',
            self::StringKeyArrayObject => 'ArrayObject<string, %s>',
            self::AnyKeyArrayObject => 'ArrayObject<array-key, %s>',
        }, $value);
    }
}
