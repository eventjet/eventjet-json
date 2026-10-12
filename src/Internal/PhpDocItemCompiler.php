<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function assert;
use function enum_exists;
use function in_array;

/**
 * Compiles PHPDoc types of collection items, such as `T` in `list<T>`. Returns null for unrecognized declarations.
 *
 * @internal
 */
final readonly class PhpDocItemCompiler
{
    /** @var class-string */
    private string $class;
    private PhpDocCollectionCompiler $collections;
    private PhpDocUnionCompiler $unions;

    public function __construct(
        private TypeCompiler $types,
        private ReflectionParameter|ReflectionProperty $field,
    ) {
        $this->class = $types->class;
        $this->collections = new PhpDocCollectionCompiler($this, $this->class, $field);
        $this->unions = new PhpDocUnionCompiler($this, $this->class);
    }

    public function collections(): PhpDocCollectionCompiler
    {
        return $this->collections;
    }

    public function unions(): PhpDocUnionCompiler
    {
        return $this->unions;
    }

    /** @throws ReflectionException */
    public function item(PhpDocType $type, string $path): Node|DecodeError|null
    {
        $nested = $this->collections->nested($type, $path);
        if ($nested !== null) {
            return $nested;
        }
        if ($type->name === '|') {
            return $this->unions->compile($type->arguments, $path);
        }
        $name = $this->name($type);
        return $name === null ? null : $this->named($name, $path);
    }

    /**
     * Resolves a scalar, enum, or class name.
     *
     * @return 'bool'|'float'|'int'|'string'|class-string|null
     */
    public function name(PhpDocType $type): string|null
    {
        if ($type->arguments === []) {
            return FieldTypeNameResolver::resolvePhpDoc($this->field, $type->name);
        }
        $isIntegerRange = $type->isIntegerRange();
        return $isIntegerRange ? 'int' : null;
    }

    /** @throws ReflectionException */
    public function named(string $name, string $path): Node|DecodeError
    {
        if (in_array($name, ['bool', 'float', 'int', 'string'], strict: true)) {
            return new ScalarNode($this->class, $name, false);
        }
        if (!enum_exists($name)) {
            $object = $this->types->object($path, $name, false);
            assert($object !== null, description: 'PHPDoc names resolve only to loaded classes.');
            return $object;
        }
        $node = EnumNode::of($this->class, $name, nullable: false);
        if ($node === null) {
            return DecodeError::nonBackedEnum($this->class, $name, $path);
        }
        return ClassFieldTypeValidator::validate($this->class, $path, $name) ?? $node;
    }
}
