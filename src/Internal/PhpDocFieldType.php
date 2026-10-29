<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\Internal\Error\DecodeFailure;
use Eventjet\Json\Internal\Error\UnsupportedField;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function str_contains;

/** @internal */
final class PhpDocFieldType
{
    /**
     * The tags of each doc comment read so far, so that a constructor's doc comment is read once for all parameters.
     *
     * @var array<string, list<PhpDocTag>>
     */
    private static array $tags = [];

    /**
     * Returns the PHPDoc type of a field: the `@var` tag that counts, or the constructor's `@param` tag that counts for a
     * parameter. Returns null if no tag counts or the decoder does not support the type of the tag that does. Each doc
     * comment is read once, even if it declares several parameters, and only if it has the tag.
     *
     * @throws DecodeFailure If the type has a number that PHP and PHPStan can read differently.
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
        $type = $tag?->type;
        $number = $type?->unsupportedNumber;
        return $number === null ? $type : throw UnsupportedField::number($field->getName(), $number);
    }

    /**
     * Returns a PHPDoc type with resolved names that is no union if it narrows a native type that names one type: it
     * names the same type, but declares the items of `array` or `ArrayObject`, or is a literal, which names the type of
     * its value. PHPDoc class names have their declared spelling; a native one may differ in case or be an alias.
     *
     * @throws ReflectionException
     */
    public static function narrowing(PhpDocType $doc, string $native): PhpDocType|null
    {
        $literal = $doc->literal;
        $declared = $literal === null
            ? PhpDocType::COLLECTIONS[$doc->name] ?? $doc->name
            : PhpDocLiteralNativeType::name($literal, [$native]);
        $canonical = match (true) {
            $literal === null && !array_key_exists($native, PhpDocType::CONTAINERS) => null,
            $declared === $native => $native,
            default => PhpDocClassNameResolver::canonical($native),
        };
        return $canonical === $declared ? $doc : null;
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
