<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
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
    private NamedTypeCompiler $names;

    /** @param class-string $class */
    public function __construct(
        private string $class,
    ) {
        $this->names = new NamedTypeCompiler($class);
    }

    /**
     * Compiles the whole type of a field. Unlike a list item, a field of a nullable class gets an object node that
     * accepts null, because lists decode their objects without a null check.
     *
     * @param string|null $label The type that errors report for a union, if it differs from the member types.
     * @throws ReflectionException
     */
    public function field(PhpDocType $type, string $path, string|null $label = null): Node|DecodeError|null
    {
        $union = $type->name === '|' ? $this->members($type->arguments, $path) : $this->compile($type, $path);
        $nullable = $union instanceof UnionBuilder ? $union->nullable() : null;
        return match (true) {
            !$union instanceof UnionBuilder => $union,
            $nullable instanceof ObjectNode => new ObjectNode($this->class, $nullable->target, nullable: true),
            $nullable instanceof UncheckedNode => $nullable,
            default => $this->union($union, $label),
        };
    }

    /** @throws ReflectionException */
    private function compile(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $arguments = $type->arguments;
        return match ($type->name) {
            '|' => $this->nested($arguments, $path),
            '&' => DecodeError::unsupportedIntersection(
                $this->class,
                $path,
                implode('&', array_map(static fn(PhpDocType $member): string => $member->name, $arguments)),
            ),
            'array{}' => $this->tuple($type->entries, $path),
            'list', 'non-empty-list' => $this->list($type, $path),
            'non-empty-array', 'non-empty-map', ArrayObject::class => $this->map($type, $path),
            'array' => null,
            default => $arguments === [] ? $this->names->compile($type->name, $path) : null,
        };
    }

    /**
     * @param list<PhpDocType> $members
     * @throws ReflectionException
     */
    private function members(array $members, string $path): UnionBuilder|DecodeError|null
    {
        $union = new UnionBuilder($this->class, $path);
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
        return $union;
    }

    /**
     * @param list<PhpDocType> $members
     * @throws ReflectionException
     */
    private function nested(array $members, string $path): Node|DecodeError|null
    {
        $union = $this->members($members, $path);
        return $union instanceof UnionBuilder ? $this->union($union, null) : $union;
    }

    /**
     * A collection or enum that may be null checks for null before its own checks.
     *
     * @throws ReflectionException
     */
    private function union(UnionBuilder $union, string|null $label): Node|DecodeError
    {
        $nullable = $union->nullable();
        return match (true) {
            $nullable instanceof EnumNode,
            $nullable instanceof ListNode,
            $nullable instanceof MapNode,
            $nullable instanceof TupleNode,
                => new NullableNode($nullable),
            default => $union->build($label),
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
        return new TupleNode($this->class, $items, $required);
    }

    /** @throws ReflectionException */
    private function list(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $arguments = $type->arguments;
        $item = count($arguments) === 1 ? $this->compile($arguments[0], $path) : null;
        return $item instanceof Node ? new ListNode($this->class, $item, $type->name === 'non-empty-list') : $item;
    }

    /** @throws ReflectionException */
    private function map(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $value = $type->stringKeyedValue();
        $node = $value === null ? null : $this->compile($value, $path);
        return $node instanceof Node ? new MapNode($this->class, $node, $type->name === ArrayObject::class) : $node;
    }
}
