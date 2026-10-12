<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strpbrk;
use function trim;

/** @internal */
final class PhpDocFieldType
{
    /**
     * Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration.
     *
     * @param class-string $class
     */
    private static function unrecognized(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s has a missing or unrecognized collection declaration. Use @%s with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        ));
    }

    /**
     * Returns the error for a field whose native type has an `array` or `ArrayObject` member that its PHPDoc type does
     * not declare. A plain `array` or `ArrayObject` field reports an unsupported map declaration in more detail.
     *
     * @param class-string $class
     * @param PhpDocType|null $declared The PHPDoc type as written.
     */
    public static function unsupported(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType|null $declared,
        PhpDocType $native,
    ): DecodeError {
        $arguments = $declared->arguments ?? [];
        $map = count($arguments) >= 2 ? $declared?->name : null;
        $stringKeys = ($arguments[0] ?? null)?->isPlainName('string') === true;
        $arrayObject = $stringKeys && $map !== null && new PhpDocTypeResolver($field)->container($map) !== null;
        return match (true) {
            $native->name === ArrayObject::class => $arrayObject
                ? self::unrecognized($class, $field)
                : self::unsupportedMap($class, $field, ArrayObject::class),
            $native->name !== 'array' => self::unrecognized($class, $field),
            $map === 'array' => DecodeError::nonInstantiableTarget($class, sprintf(
                'Field %s uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
                $field->getName(),
            )),
            in_array($map, ['non-empty-array', 'non-empty-map'], strict: true) && !$stringKeys => self::unsupportedMap(
                $class,
                $field,
                $map,
            ),
            default => self::unrecognized($class, $field),
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

    /** @param class-string $class */
    private static function unsupportedMap(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        string $type,
    ): DecodeError {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s uses unsupported map declaration %s. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
            $field->getName(),
            $type,
        ));
    }
}
