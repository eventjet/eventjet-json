<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
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
     * @param string|null $label The type that errors report for a union, if it differs from the member types.
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function field(PhpDocType $type, string $path, string|null $label = null): Node|null
    {
        return $type->name === '|' ? self::nested($type, $path, $label) : self::compile($type, $path);
    }

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function compile(PhpDocType $type, string $path): Node|null
    {
        $arguments = $type->arguments;
        return match ($type->name) {
            '|' => self::nested($type, $path),
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
     * Compiles a union. `T|null` is a nullable `T` if `T` is a collection or an enum.
     *
     * @param string|null $label The type that errors report, if it differs from the member types.
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function nested(PhpDocType $type, string $path, string|null $label = null): Node|null
    {
        $inner = $type->withoutNull();
        try {
            $nullable = $inner === null ? null : NullableNode::of(self::compile($inner, $path));
        } catch (DecodeError) {
            // The union reports the error.
            $nullable = null;
        }
        return $nullable ?? self::union($type->arguments, $path, $label);
    }

    /**
     * @param list<PhpDocType> $members
     * @param string|null $label The type that errors report, if it differs from the member types.
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function union(array $members, string $path, string|null $label): Node|null
    {
        $union = new UnionBuilder($path);
        foreach ($members as $member) {
            try {
                $node = self::compile($member, $path);
                if ($node === null) {
                    return null;
                }
                $union->add($node);
            } catch (DecodeError $error) {
                $union->reject($member->name, $error);
            }
        }
        return $union->build($label);
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
            if ($item === null) {
                return null;
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
        return $item instanceof Node ? new ListNode($item, $type->name === 'non-empty-list') : $item;
    }

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function map(PhpDocType $type, string $path): Node|null
    {
        $value = $type->stringKeyedValue();
        $node = $value === null ? null : self::compile($value, $path);
        return $node instanceof Node ? new MapNode($node, $type->name === ArrayObject::class) : $node;
    }
}
