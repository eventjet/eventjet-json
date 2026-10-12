<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function preg_match_all;
use function preg_replace;
use function sprintf;
use function str_replace;
use function trim;

/** @internal */
final class PhpDocFieldType
{
    /**
     * Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration.
     *
     * @param class-string $class
     */
    public static function unrecognized(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return DecodeError::nonInstantiableTarget($class, sprintf(
            'Field %s has a missing or unrecognized collection declaration. Use @%s with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        ));
    }

    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $doc = $field instanceof ReflectionParameter
            ? $field->getDeclaringFunction()->getDocComment()
            : $field->getDocComment();
        $doc =
            preg_replace(
                '/^[ \t]*\*[ \t]?/m',
                replacement: '',
                subject: str_replace('*/', replace: '', subject: $doc === false ? '' : $doc),
            ) ?? '';
        $matches = [];
        $pattern = $field instanceof ReflectionParameter ? '/@param\s+([^@]+)/' : '/@var\s+([^@]+)/';
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
