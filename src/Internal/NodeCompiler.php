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
    /** Whether the type has a union of several classes, which the member that #[Discriminator] names applies to. */
    public private(set) bool $discriminated = false;

    /** @param string|null $discriminator The member that #[Discriminator] names for the field. */
    public function __construct(
        private readonly string|null $discriminator = null,
    ) {}

    /**
     * Compiles the whole type of a field.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function field(PhpDocType $type, string $path): Node|null
    {
        $node = $type->name === '|' ? $this->union($type->arguments, $path) : $this->compile($type, $path);
        return $node instanceof DecodeError ? throw $node : $node;
    }

    /**
     * Compiles a type, or returns the error for a type whose values JSON cannot represent. A union skips such a member
     * if another member remains. Every other position throws the error.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private function compile(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $arguments = $type->arguments;
        return match ($type->name) {
            '|' => $this->union($arguments, $path),
            '&' => throw UnsupportedField::intersection($path, implode('&', array_map(
                static fn(PhpDocType $member): string => $member->name,
                $arguments,
            ))),
            'array{}' => $this->tuple($type->entries, $path),
            'list', 'non-empty-list' => $this->list($type, $path),
            'non-empty-array', 'non-empty-map', ArrayObject::class => $this->map($type, $path),
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
    private function union(array $members, string $path): Node|null
    {
        $union = new UnionBuilder($path, $this->discriminator);
        foreach ($members as $member) {
            $node = $this->compile($member, $path);
            if ($node instanceof DecodeError) {
                $union->reject($member->name, $node);
                continue;
            }
            if ($node === null) {
                return null;
            }
            $union->add($node);
        }
        $node = $union->build();
        $this->discriminated = $this->discriminated || $union->discriminated;
        return $node;
    }

    /**
     * @param list<PhpDocTupleEntry> $entries
     * @throws DecodeError
     * @throws ReflectionException
     */
    private function tuple(array $entries, string $path): Node|null
    {
        $required = PhpDocTupleEntry::required($entries);
        if ($required === null) {
            return null;
        }
        $items = [];
        foreach ($entries as $index => $entry) {
            $item = $this->compile($entry->type, sprintf('%s[%d]', $path, $index));
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
    private function list(PhpDocType $type, string $path): Node|null
    {
        $arguments = $type->arguments;
        $item = count($arguments) === 1 ? $this->compile($arguments[0], $path) : null;
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
    private function map(PhpDocType $type, string $path): Node|null
    {
        $value = $type->stringKeyedValue();
        $node = $value === null ? null : $this->compile($value, $path);
        return match (true) {
            $node instanceof DecodeError => throw $node,
            $node === null => null,
            default => new MapNode($node, $type->name === ArrayObject::class),
        };
    }
}
