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
    /** Builtin types that PHP checks precisely enough for JSON. */
    private const array SCALARS = ['bool' => true, 'float' => true, 'int' => true, 'string' => true];

    /** Builtin types that already include null. */
    private const array NULLS = ['mixed' => true, 'null' => true];

    /**
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function field(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node
    {
        $declared = PhpDocFieldType::resolve($field);
        $doc = $declared === null
            ? null
            : self::narrowing(new PhpDocTypeResolver($field)->resolve($declared), $field, $type);
        $node = match (true) {
            $doc !== null => NodeCompiler::field($doc, $field->getName()),
            $type instanceof ReflectionNamedType && !$type->isBuiltin() => NamedTypeCompiler::field(
                $field,
                $type,
            ) ?? $this->native($field, $type),
            default => $this->native($field, $type),
        };
        $literal =
            $node !== null && $declared !== null && $doc === null
                ? new PhpDocTypeResolver($field)->unresolvedLiteral($declared)
                : null;
        return match (true) {
            $node === null => throw CollectionDeclarationError::unsupported(
                $field,
                $declared,
                self::tree($field, $type),
            ),
            $literal !== null => throw UnsupportedField::literal($field->getName(), $literal),
            default => $node,
        };
    }

    /**
     * Compiles the native type of a field other than a class or an enum. A `bool`, `int`, `float`, or `string` field
     * becomes a ScalarNode without NodeCompiler, because compiling these fields through it slows down the first decode
     * in a request. Errors name a union as PHP spells it, and `?T` as `T|null`.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private function native(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): Node|null
    {
        if ($type instanceof ReflectionNamedType && array_key_exists($type->getName(), self::SCALARS)) {
            return new ScalarNode($type->getName(), $type->allowsNull());
        }
        $label = $type instanceof ReflectionNamedType ? null : (string) $type;
        return NodeCompiler::field(self::tree($field, $type), $field->getName(), $label);
    }

    /**
     * Returns the PHPDoc type if it narrows the native type. PhpDocNarrowing compares unions, so a field whose PHPDoc
     * type and native type each name one type doesn't load it: loading a class is a measurable part of the first
     * decode in a request.
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

    /**
     * Returns a native type as the type tree that PHPDoc types resolve to, with `?T` as `T|null`, and `true` and `false`
     * as literals.
     */
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
        $named = PhpDocType::named(FieldTypeNameResolver::resolve($field, $type));
        $nullable = $type->allowsNull() && !array_key_exists($type->getName(), self::NULLS);
        return $nullable ? new PhpDocType('|', [$named, new PhpDocType('null')]) : $named;
    }
}
