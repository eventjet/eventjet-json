<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\ItemNode;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;
use function sprintf;
use function strcasecmp;

/**
 * Compiles a field whose native type is `array` or `ArrayObject`, using its PHPDoc collection declaration.
 *
 * @internal
 */
final readonly class CollectionCompiler
{
    /** @var class-string */
    private string $class;
    private PhpDocCollectionCompiler $collections;

    public function __construct(
        TypeCompiler $types,
        private ReflectionParameter|ReflectionProperty $field,
    ) {
        $this->class = $types->class;
        $this->collections = new PhpDocItemCompiler($types, $field)->collections();
    }

    /** @throws ReflectionException */
    public function compile(string $native): ItemNode|DecodeError
    {
        $doc = PhpDocFieldType::resolve($this->field);
        $node = $native === ArrayObject::class ? $this->arrayObject($doc) : $this->array($doc);
        return $node ?? PhpDocFieldType::unrecognized($this->class, $this->field);
    }

    /** @throws ReflectionException */
    private function array(PhpDocType|null $doc): ItemNode|DecodeError|null
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
            return DecodeError::nonInstantiableTarget(
                $this->class,
                sprintf(
                    'Field %s uses array<TKey, TValue>, whose empty value encodes as a JSON array and cannot represent an empty JSON object. Use non-empty-array<string, TValue> for a non-empty map or ArrayObject<string, TValue> for a map that may be empty.',
                    $path,
                ),
            );
        }
        return $this->stringKeyedMap($doc, $doc->name);
    }

    /** @throws ReflectionException */
    private function arrayObject(PhpDocType|null $doc): ItemNode|DecodeError|null
    {
        $arguments = $doc->arguments ?? [];
        if ($doc === null || count($arguments) < 2) {
            return $this->unsupportedMap(ArrayObject::class);
        }
        $container = FieldTypeNameResolver::resolvePhpDoc($this->field, $doc->name) ?? $doc->name;
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
    private function stringKeyedMap(PhpDocType $doc, string $container): ItemNode|DecodeError|null
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
        return DecodeError::nonInstantiableTarget(
            $this->class,
            sprintf(
                'Field %s uses unsupported map declaration %s. Maps must use non-empty-array<string, TValue> or ArrayObject<string, TValue>.',
                $this->field->getName(),
                $type,
            ),
        );
    }
}
