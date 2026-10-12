<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_any;
use function assert;
use function in_array;

/**
 * Compiles field declarations of one class into decoder nodes. A field's PHPDoc type applies if it narrows the native
 * type; otherwise the native type applies.
 *
 * @internal
 */
final readonly class TypeCompiler
{
    /** @param class-string $class */
    public function __construct(
        public string $class,
    ) {}

    /** @throws ReflectionException */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
    {
        $native = self::tree($field, $type);
        $declared = self::declared($field, $native);
        $doc = $declared === null
            ? null
            : PhpDocNarrowing::narrowing(new PhpDocTypeResolver($field)->resolve($declared), $native);
        $node = $doc === null
            ? $this->native($field, $type, $native)
            : new NodeCompiler($this->class)->field($doc, $field->getName());
        $literal =
            $node instanceof Node && $declared !== null && $doc === null
                ? new PhpDocTypeResolver($field)->unresolvedLiteral($declared)
                : null;
        return match (true) {
            $node === null => PhpDocFieldType::unsupported($this->class, $field, $declared, $native),
            $literal !== null => DecodeError::nonInstantiableField(
                $this->class,
                $field->getName(),
                'unsupported literal declaration',
                $literal,
            ),
            default => $node,
        };
    }

    /**
     * Compiles the native type of a field. PHP checks builtin scalar types itself.
     *
     * @throws ReflectionException
     */
    private function native(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
        PhpDocType $native,
    ): Node|DecodeError|null {
        $scalar =
            $type instanceof ReflectionNamedType
            && $type->isBuiltin()
            && !in_array($type->getName(), ['array', 'mixed', 'object'], strict: true);
        return $scalar
            ? new ScalarNode($this->class, $type->getName(), $type->allowsNull())
            : new NodeCompiler($this->class)->field($native, $field->getName(), (string) $type);
    }

    /**
     * Returns the PHPDoc type of a field if it may declare something that its native type cannot: the items of an
     * `array` or `ArrayObject` member, or literals.
     */
    private static function declared(ReflectionParameter|ReflectionProperty $field, PhpDocType $native): PhpDocType|null
    {
        $collection = array_any($native->members(), static fn(PhpDocType $member): bool => in_array(
            $member->name,
            ['array', ArrayObject::class],
            strict: true,
        ));
        $mayDeclare =
            $collection || FieldTypeNameResolver::mayDeclareLiteral(FieldTypeNameResolver::docComment($field));
        return $mayDeclare ? PhpDocFieldType::resolve($field) : null;
    }

    /** Returns a native type as the type tree that PHPDoc types parse into, with `?T` as `T|null`. */
    private static function tree(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): PhpDocType
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $members = [];
            foreach ($type->getTypes() as $member) {
                $members[] = self::tree($field, $member);
            }
            $operator = match (true) {
                $type instanceof ReflectionUnionType => '|',
                default => '&',
            };
            return new PhpDocType($operator, $members);
        }
        assert($type instanceof ReflectionNamedType, description: 'Other types are unions or intersections.');
        $named = new PhpDocType(FieldTypeNameResolver::resolve($field, $type));
        $nullable = $type->allowsNull() && !in_array($type->getName(), ['mixed', 'null'], strict: true);
        return $nullable ? new PhpDocType('|', [$named, new PhpDocType('null')]) : $named;
    }
}
