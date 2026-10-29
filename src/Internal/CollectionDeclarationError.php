<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;

/** @internal */
final class CollectionDeclarationError
{
    /**
     * Returns the error for a field whose native type has an `array` or `ArrayObject` member that its PHPDoc type does
     * not declare. A plain `array` or `ArrayObject` field reports an unsupported map declaration in more detail.
     *
     * @param PhpDocType|null $declared The PHPDoc type as written.
     */
    public static function unsupported(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType|null $declared,
        PhpDocType $native,
    ): DecodeError {
        $arguments = $declared->arguments ?? [];
        $map = count($arguments) >= 2 ? $declared?->name : null;
        $stringKeys = ($arguments[0] ?? null)?->isPlainName('string') === true;
        $arrayObject = $stringKeys && $map !== null && new PhpDocTypeResolver($field)->container($map) !== null;
        $name = $field->getName();
        return match (true) {
            $native->name === ArrayObject::class => $arrayObject
                ? self::unrecognized($field)
                : UnsupportedCollection::map($name, ArrayObject::class),
            $native->name !== 'array' => self::unrecognized($field),
            $map === 'array' => UnsupportedCollection::genericArrayMap($name),
            in_array($map, ['non-empty-array', 'non-empty-map'], strict: true) && !$stringKeys
                => UnsupportedCollection::map($name, $map),
            default => self::unrecognized($field),
        };
    }

    /** Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration. */
    private static function unrecognized(ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return UnsupportedCollection::unrecognized(
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        );
    }
}
