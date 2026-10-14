<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\ScalarNode;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function assert;
use function in_array;

/**
 * Compiles field declarations into decoder nodes. A field's PHPDoc type applies if it narrows the native
 * type; otherwise the native type applies.
 *
 * @internal
 */
final readonly class TypeCompiler
{
    /** @throws ReflectionException */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
    {
        $native = self::tree($field, $type);
        $declared = PhpDocFieldType::resolve($field);
        $doc = $declared === null
            ? null
            : PhpDocNarrowing::narrowing(new PhpDocTypeResolver($field)->resolve($declared), $native);
        $node = $doc === null
            ? $this->native($field, $type, $native)
            : new NodeCompiler()->field($doc, $field->getName());
        $literal =
            $node instanceof Node && $declared !== null && $doc === null
                ? new PhpDocTypeResolver($field)->unresolvedLiteral($declared)
                : null;
        return match (true) {
            $node === null => CollectionDeclarationError::unsupported($field, $declared, $native),
            $literal !== null => UnsupportedField::literal($field->getName(), $literal),
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
            ? new ScalarNode($type->getName(), $type->allowsNull())
            : new NodeCompiler()->field($native, $field->getName(), (string) $type);
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
