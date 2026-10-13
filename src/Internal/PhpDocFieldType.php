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
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocFieldType
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

    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $doc = FieldTypeNameResolver::docComment($field);
        $doc =
            preg_replace('/^[ \t]*\*[ \t]?/m', replacement: '', subject: str_replace('*/', replace: '', subject: $doc))
            ?? '';
        $matches = [];
        $tag = $field instanceof ReflectionParameter ? 'param' : 'var';
        // Quoted literals may contain "@", so each source then extends to the end and the parser finds where the type ends.
        $quoted = strpbrk($doc, characters: "'\"") !== false;
        $pattern = $quoted ? '/@' . $tag . '\s+(?=([\s\S]*))/' : '/@' . $tag . '\s+([^@]+)/';
        preg_match_all($pattern, $doc, $matches);

        /** @var array{list<string>, list<string>} $matches */
        $sources = $matches[1];
        foreach ($sources as $source) {
            $parsed = PhpDocTypeParser::prefix(trim($source, characters: " \t\r\n\v\f"));
            if ($parsed === null) {
                continue;
            }

            [$type, $remainder] = $parsed;
            $suffixPattern = $field instanceof ReflectionParameter
                ? '/\\A\\s+\\$' . $field->getName() . '(?:\\s|$)/'
                : '/\\A(?:\\s+[^\\s|&<>\\[\\],?:{}].*)?\\z/s';
            $validSuffix = preg_match($suffixPattern, $remainder) === 1;

            if ($validSuffix) {
                return $type;
            }
        }

        return null;
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
