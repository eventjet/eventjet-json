<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function trim;

/** @internal */
final class PhpDocFieldType
{
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
