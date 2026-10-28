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

use function array_column;
use function array_key_exists;
use function assert;

/**
 * Compiles field declarations into decoder nodes. A field's PHPDoc type applies if it narrows the native
 * type; otherwise the native type applies.
 *
 * @internal
 */
final readonly class TypeCompiler
{
    /** Builtin types that PHP does not check precisely enough for JSON. */
    private const array IMPRECISE = [
        'array' => true,
        'callable' => true,
        'iterable' => true,
        'mixed' => true,
        'object' => true,
    ];

    /** Builtin types that already include null. */
    private const array NULLS = ['mixed' => true, 'null' => true];

    /** @throws ReflectionException */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError
    {
        $declared = PhpDocFieldType::resolve($field);
        if ($declared instanceof DecodeError) {
            return $declared;
        }
        $doc = $declared === null
            ? null
            : self::narrowing(new PhpDocTypeResolver($field)->resolve($declared), $field, $type);
        $node = match (true) {
            $doc !== null => NodeCompiler::field($doc, $field->getName()),
            $type instanceof ReflectionNamedType => NamedTypeCompiler::field($field, $type) ?? $this->native(
                $field,
                $type,
            ),
            default => $this->native($field, $type),
        };
        $literal =
            $node instanceof Node && $declared !== null && $doc === null
                ? new PhpDocTypeResolver($field)->unresolvedLiteral($declared)
                : null;
        return match (true) {
            $node === null => CollectionDeclarationError::unsupported($field, $declared, self::tree($field, $type)),
            $literal !== null => UnsupportedField::literal($field->getName(), $literal),
            default => $node,
        };
    }

    /**
     * Compiles the native type of a field other than a class or an enum. PHP checks builtin scalar types itself.
     *
     * @throws ReflectionException
     */
    private function native(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|DecodeError|null
    {
        $scalar =
            $type instanceof ReflectionNamedType
            && $type->isBuiltin()
            && !array_key_exists($type->getName(), self::IMPRECISE);
        return $scalar
            ? new ScalarNode($type->getName(), $type->allowsNull())
            : NodeCompiler::field(self::tree($field, $type), $field->getName(), (string) $type);
    }

    /**
     * Returns the PHPDoc type if it narrows the native type. PhpDocNarrowing compares unions, so a field whose PHPDoc
     * type and native type each name one type doesn't load it.
     *
     * @throws ReflectionException
     */
    private static function narrowing(
        PhpDocType|null $doc,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
    ): PhpDocType|null {
        return match (true) {
            $doc === null => null,
            $doc->name === '|',
            !$type instanceof ReflectionNamedType,
            $type->allowsNull(),
                => PhpDocNarrowing::narrowing($doc, array_column(self::tree($field, $type)->members(), 'name')),
            default => PhpDocFieldType::narrowing($doc, FieldTypeNameResolver::resolve($field, $type)),
        };
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
        $nullable = $type->allowsNull() && !array_key_exists($type->getName(), self::NULLS);
        return $nullable ? new PhpDocType('|', [$named, new PhpDocType('null')]) : $named;
    }
}
