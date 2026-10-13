<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\LiteralNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullOnlyNode;
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
        return $name === null ? $this->unions->compile([$type], $path) : $this->named($name, $path);
    }

    /**
     * Returns the literal names that a PHPDoc type stands for, expanding constants, or null if it is no literal.
     *
     * @return non-empty-list<string>|null
     * @throws ReflectionException
     */
    public function literals(PhpDocType $type): array|null
    {
        return $type->arguments === [] ? PhpDocConstantResolver::literals($this->field, $type->name) : null;
    }

    /**
     * Resolves a scalar, enum, or class name.
     *
     * @return 'bool'|'float'|'int'|'string'|class-string|null
     */
    public function name(PhpDocType $type): string|null
    {
        if ($type->arguments === []) {
            return PhpDocFieldType::resolveName($this->field, $type->name);
        }
        $isIntegerRange = $type->isIntegerRange();
        return $isIntegerRange ? 'int' : null;
    }

    /** @throws ReflectionException */
    private function named(string $name, string $path): Node|DecodeError
    {
        if (in_array($name, ['bool', 'float', 'int', 'string'], strict: true)) {
            return new ScalarNode($this->class, $name, false);
        }
        if (!enum_exists($name)) {
            $object = $this->types->object($path, $name, false);
            assert($object !== null, description: 'PHPDoc names resolve only to loaded classes.');
            return $object;
        }
        $node = EnumNode::of($this->class, $name);
        if ($node === null) {
            return DecodeError::nonBackedEnum($this->class, $name, $path);
        }
        return ClassFieldTypeValidator::validate($this->class, $path, $name) ?? $node;
    }

    /**
     * Compiles a member that is a name: a literal, a scalar, an enum, or a class.
     *
     * @throws ReflectionException
     */
    public function member(string $member, string $path): Node|DecodeError
    {
        $literal = $member === 'null' || PhpDocType::literalSyntax($member);
        return $literal ? $this->literal($member) : $this->named($member, $path);
    }

    /** Compiles null or a literal that resolves to a value. */
    public function literal(string $name): Node
    {
        if ($name === 'null') {
            return new NullOnlyNode($this->class, 'null');
        }
        return LiteralNode::of($this->class, $name);
    }
}
