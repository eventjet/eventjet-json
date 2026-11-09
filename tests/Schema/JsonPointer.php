<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use InvalidArgumentException;
use stdClass;

use function array_key_exists;
use function array_map;
use function explode;
use function implode;
use function is_array;
use function rawurldecode;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;

/** @internal */
final class JsonPointer
{
    /**
     * @return list<string>
     * @throws InvalidArgumentException
     */
    public static function parse(string $pointer): array
    {
        if ($pointer === '') {
            return [];
        }
        $isPointer = str_starts_with($pointer, '/');
        if (!$isPointer) {
            throw new InvalidArgumentException('"' . $pointer . '" is not a JSON Pointer.');
        }
        return array_map(
            static fn(string $part): string => str_replace(['~1', '~0'], ['/', '~'], $part),
            explode('/', substr($pointer, offset: 1)),
        );
    }

    /**
     * Returns the reference tokens of the fragment of a URI.
     *
     * @return list<string>
     * @throws InvalidArgumentException
     */
    public static function parseFragment(string $uri): array
    {
        $hash = strpos($uri, needle: '#');
        return self::parse($hash === false ? '' : rawurldecode(substr($uri, $hash + 1)));
    }

    /** @param list<string> $parts */
    public static function format(array $parts): string
    {
        $escaped = array_map(static fn(string $part): string => str_replace(['~', '/'], ['~0', '~1'], $part), $parts);
        return $escaped === [] ? '' : '/' . implode('/', $escaped);
    }

    /**
     * Returns the value the reference tokens lead to in a JSON document, as a list of one value, or an empty list if
     * they lead nowhere.
     *
     * @param list<string> $parts
     * @return array{}|array{mixed}
     */
    public static function find(mixed $document, array $parts): array
    {
        foreach ($parts as $part) {
            $members = $document instanceof stdClass || is_array($document) ? (array) $document : [];
            $exists = array_key_exists($part, $members);
            if (!$exists) {
                return [];
            }
            /** @var mixed $document */
            $document = $members[$part] ?? null;
        }
        return [$document];
    }

    /** Returns whether a JSON Pointer points to the given one or into it. */
    public static function within(string $pointer, string $ancestor): bool
    {
        return $pointer === $ancestor || str_starts_with($pointer, $ancestor . '/');
    }
}
