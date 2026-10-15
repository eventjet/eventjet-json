<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedFieldClass;
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
    private PhpDocCollectionCompiler $collections;
    private PhpDocUnionCompiler $unions;

    public function __construct(
        private ReflectionParameter|ReflectionProperty $field,
    ) {
        $this->collections = new PhpDocCollectionCompiler($this, $field);
        $this->unions = new PhpDocUnionCompiler($this);
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
     * Returns the literals, or `null`, that a PHPDoc type stands for, expanding constants. Returns null if it stands for
     * none.
     *
     * @return non-empty-list<PhpDocLiteral|'null'>|null
     * @throws ReflectionException
     */
    public function literals(PhpDocType $type): array|null
    {
        if ($type->literal !== null) {
            return [$type->literal];
        }
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
            return FieldTypeNameResolver::resolvePhpDoc($this->field, $type->name);
        }
        $isIntegerRange = PhpDocIntegerRange::matches($type);
        return $isIntegerRange ? 'int' : null;
    }

    /** @throws ReflectionException */
    private function named(string $name, string $path): Node|DecodeError
    {
        if (in_array($name, ['bool', 'float', 'int', 'string'], strict: true)) {
            return new ScalarNode($name, false);
        }
        if (!enum_exists($name)) {
            $object = ClassFieldTypeValidator::object($path, $name, false);
            assert($object !== null, description: 'PHPDoc names resolve only to loaded classes.');
            return $object;
        }
        $node = EnumNode::of($name);
        if ($node === null) {
            return UnsupportedFieldClass::nonBackedEnum($path, $name);
        }
        return ClassFieldTypeValidator::validate($path, $name) ?? $node;
    }

    /**
     * Compiles a member that is not a collection: a literal, null, a scalar, an enum, or a class.
     *
     * @throws ReflectionException
     */
    public function member(PhpDocLiteral|string $member, string $path): Node|DecodeError
    {
        return $member instanceof PhpDocLiteral || $member === 'null'
            ? $this->literal($member)
            : $this->named($member, $path);
    }

    /** Compiles a literal or null. */
    public function literal(PhpDocLiteral|string $member): Node
    {
        return $member instanceof PhpDocLiteral ? LiteralNode::of($member) : new NullOnlyNode('null');
    }
}
