<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use Eventjet\Json\Internal\Error\UnsupportedField;
use ReflectionParameter;
use ReflectionProperty;

use function str_contains;
use function strpbrk;

/** @internal */
final class PhpDocFieldType
{
    /**
     * The tags of each doc comment read so far, so that a constructor's doc comment is read once for all parameters.
     *
     * @var array<string, list<PhpDocTag>>
     */
    private static array $tags = [];

    /** Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration. */
    public static function unrecognized(ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return UnsupportedCollection::unrecognized(
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        );
    }

    /**
     * Returns the PHPDoc type of a field: the `@var` tag that counts, or the constructor's `@param` tag that counts for a
     * parameter. Returns null if no tag counts or the decoder does not support the type of the tag that does. Each doc
     * comment is read once, even if it declares several parameters, and only if it has the tag.
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $parameter = $field instanceof ReflectionParameter;
        $doc = self::docComment($field);
        if (!str_contains($doc, $parameter ? 'param' : 'var')) {
            return null;
        }
        self::$tags[$doc] ??= PhpDocSimpleTags::tags($doc) ?? PhpDocTagParser::tags($doc);
        $tags = self::$tags[$doc];
        $tag = $parameter
            ? PhpDocTag::forParameter($tags, $field->getName())
            : PhpDocTag::forProperty($tags, $field->getName());
        return $tag?->type;
    }

    /** Returns an error if a field's PHPDoc type has a number literal that PHP and PHPStan can read differently. */
    public static function unsupportedNumber(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): DecodeError|null {
        // Every number literal has a digit, so a doc comment without digits has none.
        $digits = strpbrk(self::docComment($field), characters: '0123456789') !== false;
        $number = $digits ? PhpDocNumbers::unsupported($type) : null;
        return $number === null ? null : UnsupportedField::number($field->getName(), $number);
    }

    /** Returns the doc comment that declares a field: the constructor's for a parameter. */
    private static function docComment(ReflectionParameter|ReflectionProperty $field): string
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        return (string) $doc;
    }
}
