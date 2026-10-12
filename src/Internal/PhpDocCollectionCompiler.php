<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\ListNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullableNode;
use Eventjet\Json\Internal\Node\TupleNode;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;
use function sprintf;
use function strcasecmp;

/**
 * Compiles PHPDoc lists, maps, and tuples. Returns null for declarations that are not one of these collections.
 *
 * @internal
 */
final readonly class PhpDocCollectionCompiler
{
    /** @param class-string $class */
    public function __construct(
        private PhpDocItemCompiler $items,
        private string $class,
        private ReflectionParameter|ReflectionProperty $field,
    ) {}

    /**
     * Compiles a collection, optionally combined with null, such as `list<int>|null`.
     *
     * @throws ReflectionException
     */
    public function nested(PhpDocType $type, string $path): Node|DecodeError|null
    {
        if ($type->name !== '|') {
            return $this->collection($type, $path);
        }
        $inner = $type->withoutNull();
        $collection = $inner === null ? null : $this->collection($inner, $path);
        return $collection instanceof Node ? new NullableNode($collection) : $collection;
    }

    /** @throws ReflectionException */
    public function collection(PhpDocType $type, string $path): Node|DecodeError|null
    {
        if ($type->name === 'array{}') {
            return $this->tuple($type, $path);
        }
        $arguments = $type->arguments;
        if (in_array($type->name, ['list', 'non-empty-list'], strict: true) && count($arguments) === 1) {
            $item = $this->items->item($arguments[0], $path);
            return $item instanceof Node ? new ListNode($this->class, $item, $type->name === 'non-empty-list') : $item;
        }
        $value = $type->stringKeyedValue();
        if ($value === null) {
            return null;
        }
        $container = FieldTypeNameResolver::resolvePhpDoc($this->field, $type->name) ?? $type->name;
        $isMap = in_array($type->name, ['non-empty-array', 'non-empty-map'], strict: true);
        return $isMap || strcasecmp($container, ArrayObject::class) === 0
            ? $this->map($container, $value, $path)
            : null;
    }

    /**
     * Compiles a map with the given value type, where the container is `ArrayObject` or a non-empty array.
     *
     * @throws ReflectionException
     */
    public function map(string $container, PhpDocType $value, string $path): Node|DecodeError|null
    {
        $node = $this->items->item($value, $path);
        $arrayObject = strcasecmp($container, ArrayObject::class) === 0;
        return $node instanceof Node ? new MapNode($this->class, $node, $arrayObject) : $node;
    }

    /** @throws ReflectionException */
    private function tuple(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $required = PhpDocTupleEntry::required($type->entries);
        if ($required === null) {
            return null;
        }
        $items = [];
        foreach ($type->entries as $index => $entry) {
            $item = $this->items->item($entry->type, sprintf('%s[%d]', $path, $index));
            if (!$item instanceof Node) {
                return $item;
            }
            $items[] = $item;
        }
        return new TupleNode($this->class, $items, $required);
    }
}
