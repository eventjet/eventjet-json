<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use Eventjet\Json\Internal\Error\UnsupportedField;
use ReflectionParameter;
use ReflectionProperty;

use function str_contains;

/** @internal */
final class PhpDocFieldType
{
    /**
     * The tags of each doc comment read so far, so that a constructor's doc comment is read once for all parameters.
     *
     * @var array<string, list<array{string, string, PhpDocType|null}>>
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
     * parameter. Each doc comment is read once, even if it declares several parameters, and only if it has the tag.
     * Returns an error if the type has a number that PHP and PHPStan can read differently.
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|DecodeError|null
    {
        $parameter = $field instanceof ReflectionParameter;
        $doc = self::docComment($field);
        if (!str_contains($doc, $parameter ? 'param' : 'var')) {
            return null;
        }
        self::$tags[$doc] ??= PhpDocSimpleTags::tags($doc) ?? PhpDocTagParser::tags($doc);
        $tags = self::$tags[$doc];
        $type = $parameter
            ? PhpDocTagSelector::parameter($tags, $field->getName())
            : PhpDocTagSelector::property($tags, $field->getName());
        $number = $type?->unsupportedNumber;
        return $number === null ? $type : UnsupportedField::number($field->getName(), $number);
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
