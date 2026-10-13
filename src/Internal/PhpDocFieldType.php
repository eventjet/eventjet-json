<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocFieldType
{
    /** Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration. */
    public static function unrecognized(ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return UnsupportedCollection::unrecognized(
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        );
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
}
