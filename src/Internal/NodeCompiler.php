<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\ObjectNode;
use Eventjet\Json\Internal\Node\TupleNode;
use Eventjet\Json\Internal\Node\UncheckedNode;
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
final readonly class NodeCompiler
{
    /**
     * Compiles the whole type of a field. Unlike a list item, a field of a nullable class gets an object node that
     * accepts null, because lists decode their objects without a null check.
     *
     * @param string|null $label The type that errors report for a union, if it differs from the member types.
     * @throws ReflectionException
     */
    public function field(PhpDocType $type, string $path, string|null $label = null): Node|DecodeError|null
    {
        $inner = $type->withoutNull();
        $node = $inner === null ? null : $this->compile($inner, $path);
        return match (true) {
            $node instanceof ObjectNode => new ObjectNode($node->target, nullable: true),
            $node instanceof UncheckedNode => $node,
            $type->name === '|' => self::nullable($node) ?? $this->union($type->arguments, $path, $label),
            default => $this->compile($type, $path),
        };
    }

    /** @throws ReflectionException */
    private function compile(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $arguments = $type->arguments;
        return match ($type->name) {
            '|' => $this->nested($type, $path),
            '&' => UnsupportedField::intersection($path, implode('&', array_map(
                static fn(PhpDocType $member): string => $member->name,
                $arguments,
            ))),
            'array{}' => $this->tuple($type->entries, $path),
            'list', 'non-empty-list' => $this->list($type, $path),
            'non-empty-array', 'non-empty-map', ArrayObject::class => $this->map($type, $path),
            'array' => null,
            default => $arguments === [] ? NamedTypeCompiler::compile($type->name, $path) : null,
        };
    }

    /** @throws ReflectionException */
    private function nested(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $inner = $type->withoutNull();
        $nullable = self::nullable($inner === null ? null : $this->compile($inner, $path));
        return $nullable ?? $this->union($type->arguments, $path, null);
    }

    /**
     * @param list<PhpDocType> $members
     * @param string|null $label The type that errors report, if it differs from the member types.
     * @throws ReflectionException
     */
    private function union(array $members, string $path, string|null $label): Node|DecodeError|null
    {
        $union = new UnionBuilder($path);
        foreach ($members as $member) {
            $node = $this->compile($member, $path);
            $rejected = NamedTypeCompiler::unrepresentable($member, $node);
            if ($rejected !== null) {
                $union->reject($member->name, $rejected);
                continue;
            }
            if (!$node instanceof Node) {
                return $node;
            }
            $union->add($node);
        }
        return $union->build($label);
    }

    /** A collection or enum that may be null checks for null before its own checks. */
    private static function nullable(Node|DecodeError|null $node): NullableNode|null
    {
        return match (true) {
            $node instanceof EnumNode,
            $node instanceof ListNode,
            $node instanceof MapNode,
            $node instanceof TupleNode,
                => new NullableNode($node),
            default => null,
        };
    }

    /**
     * @param list<PhpDocTupleEntry> $entries
     * @throws ReflectionException
     */
    private function tuple(array $entries, string $path): Node|DecodeError|null
    {
        $required = PhpDocTupleEntry::required($entries);
        if ($required === null) {
            return null;
        }
        $items = [];
        foreach ($entries as $index => $entry) {
            $item = $this->compile($entry->type, sprintf('%s[%d]', $path, $index));
            if (!$item instanceof Node) {
                return $item;
            }
            $items[] = $item;
        }
        return new TupleNode($items, $required);
    }

    /** @throws ReflectionException */
    private function list(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $arguments = $type->arguments;
        $item = count($arguments) === 1 ? $this->compile($arguments[0], $path) : null;
        return $item instanceof Node ? new ListNode($item, $type->name === 'non-empty-list') : $item;
    }

    /** @throws ReflectionException */
    private function map(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $value = $type->stringKeyedValue();
        $node = $value === null ? null : $this->compile($value, $path);
        return $node instanceof Node ? new MapNode($node, $type->name === ArrayObject::class) : $node;
    }
}
