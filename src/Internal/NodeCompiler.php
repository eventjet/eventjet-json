<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\TupleNode;
use ReflectionException;

use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * Compiles a type with resolved names, native or from PHPDoc, into decoder nodes, rejecting declarations that cannot
 * round-trip. Returns null for `array` and `ArrayObject` types without a supported item declaration.
 *
 * @internal
 */
final class NodeCompiler
{
    /**
     * Compiles the whole type of a field.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function field(PhpDocType $type, string $path): Node|null
    {
        $node = $type->name === '|' ? self::union($type->arguments, $path) : self::compile($type, $path);
        return $node instanceof DecodeError ? throw $node : $node;
    }

    /**
     * Compiles a type, or returns the error for a type whose values JSON cannot represent. A union skips such a member
     * if another member remains. Every other position throws the error.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function compile(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $arguments = $type->arguments;
        return match ($type->name) {
            '|' => self::union($arguments, $path),
            '&' => throw UnsupportedField::intersection($path, implode('&', array_map(
                static fn(PhpDocType $member): string => $member->name,
                $arguments,
            ))),
            'array{}' => self::tuple($type->entries, $path),
            'list', 'non-empty-list' => self::list($type, $path),
            'non-empty-array', 'non-empty-map', ArrayObject::class => self::map($type, $path),
            'array' => null,
            default => $arguments === [] ? NamedTypeCompiler::compile($type, $path) : null,
        };
    }

    /**
     * Compiles a union. The union builder skips a member whose values JSON cannot represent if another member remains.
     *
     * @param list<PhpDocType> $members
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function union(array $members, string $path): Node|null
    {
        $union = new UnionBuilder($path);
        foreach ($members as $member) {
            $node = self::compile($member, $path);
            if ($node instanceof DecodeError) {
                $union->reject($member->name, $node);
                continue;
            }
            if ($node === null) {
                return null;
            }
            $union->add($node);
        }
        return $union->build();
    }

    /**
     * @param list<PhpDocTupleEntry> $entries
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function tuple(array $entries, string $path): Node|null
    {
        $required = PhpDocTupleEntry::required($entries);
        if ($required === null) {
            return null;
        }
        $items = [];
        foreach ($entries as $index => $entry) {
            $item = self::compile($entry->type, sprintf('%s[%d]', $path, $index));
            if (!$item instanceof Node) {
                return $item === null ? null : throw $item;
            }
            $items[] = $item;
        }
        return new TupleNode($items, $required);
    }

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function list(PhpDocType $type, string $path): Node|null
    {
        $arguments = $type->arguments;
        $item = count($arguments) === 1 ? self::compile($arguments[0], $path) : null;
        return match (true) {
            $item instanceof DecodeError => throw $item,
            $item === null => null,
            default => new ListNode($item, $type->name === 'non-empty-list'),
        };
    }

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function map(PhpDocType $type, string $path): Node|null
    {
        $value = $type->stringKeyedValue();
        $node = $value === null ? null : self::compile($value, $path);
        return match (true) {
            $node instanceof DecodeError => throw $node,
            $node === null => null,
            default => new MapNode($node, $type->name === ArrayObject::class),
        };
    }
}
