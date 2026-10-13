<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;
use function strcasecmp;

/**
 * Compiles a field whose native type is `array` or `ArrayObject`, using its PHPDoc collection declaration.
 *
 * @internal
 */
final readonly class CollectionCompiler
{
    private PhpDocCollectionCompiler $collections;

    public function __construct(
        TypeCompiler $types,
        private ReflectionParameter|ReflectionProperty $field,
    ) {
        $this->collections = new PhpDocItemCompiler($types, $field)->collections();
    }

    /** @throws ReflectionException */
    public function compile(string $native): Node|DecodeError
    {
        $doc = FieldTypeNameResolver::phpDoc($this->field);
        $node = $native === ArrayObject::class ? $this->arrayObject($doc) : $this->array($doc);
        return $node ?? PhpDocFieldType::unrecognized($this->field);
    }

    /** @throws ReflectionException */
    private function array(PhpDocType|null $doc): Node|DecodeError|null
    {
        if ($doc === null) {
            return null;
        }
        $path = $this->field->getName();
        if (in_array($doc->name, ['array{}', 'list', 'non-empty-list'], strict: true)) {
            return $this->collections->collection($doc, $path);
        }
        $arguments = $doc->arguments;
        if (
            count($arguments) < 2
            || !in_array($doc->name, ['array', 'non-empty-array', 'non-empty-map'], strict: true)
        ) {
            return null;
        }
        if ($doc->name === 'array') {
            return UnsupportedCollection::genericArrayMap($path);
        }
        return $this->stringKeyedMap($doc, $doc->name);
    }

    /** @throws ReflectionException */
    private function arrayObject(PhpDocType|null $doc): Node|DecodeError|null
    {
        $arguments = $doc->arguments ?? [];
        if ($doc === null || count($arguments) < 2) {
            return $this->unsupportedMap(ArrayObject::class);
        }
        $container = PhpDocFieldType::resolveName($this->field, $doc->name) ?? $doc->name;
        if (strcasecmp($container, ArrayObject::class) !== 0) {
            return $this->unsupportedMap(ArrayObject::class);
        }
        return $this->stringKeyedMap($doc, ArrayObject::class);
    }

    /**
     * Compiles a map declaration with at least two type arguments, which must be `<string, T>`.
     *
     * @throws ReflectionException
     */
    private function stringKeyedMap(PhpDocType $doc, string $container): Node|DecodeError|null
    {
        $arguments = $doc->arguments;
        $hasStringKey = ($arguments[0] ?? null)?->isPlainName('string') === true;
        if (!$hasStringKey) {
            return $this->unsupportedMap($container);
        }
        $value = $doc->stringKeyedValue();
        return $value === null ? null : $this->collections->map($container, $value, $this->field->getName());
    }

    private function unsupportedMap(string $type): DecodeError
    {
        return UnsupportedCollection::map($this->field->getName(), $type);
    }
}
