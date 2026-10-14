<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_any;
use function array_keys;
use function enum_exists;
use function implode;
use function is_array;

/**
 * Compiles a scalar, enum, or union field with a PHPDoc type, which may restrict it to literals, such as
 * `'draft'|'published'`.
 *
 * @internal
 */
final class LiteralFieldCompiler
{
    /**
     * Compiles the native declaration if the PHPDoc declares no literal.
     *
     * @throws ReflectionException
     */
    public static function compile(
        TypeCompiler $types,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
        PhpDocType $doc,
    ): Node|DecodeError {
        $path = $field->getName();
        $declared = $doc->members();
        $items = new PhpDocItemCompiler($types, $field);
        $members = $items->unions()->members($declared, $path);
        if (!is_array($members)) {
            return $members ?? self::native($types, $field, $type, $declared, $items);
        }
        $declaresLiteral = self::declaresLiteral($members);
        if (!$declaresLiteral) {
            return $types->declared($field, $type);
        }
        $native = $type instanceof ReflectionUnionType
            ? NativeUnionCompiler::classes($types, $field, $type)
            : $types->declared($field, $type);
        return $native instanceof DecodeError ? $native : $items->unions()->build($members, $path);
    }

    /**
     * Returns the PHPDoc type of a field whose native type literals can restrict, or null if the native declaration is
     * the whole declaration.
     */
    public static function phpDoc(ReflectionParameter|ReflectionProperty $field, ReflectionType $type): PhpDocType|null
    {
        $accepts = self::accepts($type);
        return $accepts ? PhpDocFieldType::resolve($field) : null;
    }

    /**
     * Literals restrict scalars and backed enums. Collection items declare their literals in the collection type.
     */
    private static function accepts(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return !NativeUnionCompiler::hasCollection($type);
        }
        return (
            $type instanceof ReflectionNamedType
            && ($type->isBuiltin() ? $type->getName() !== 'array' : enum_exists($type->getName()))
        );
    }

    /** @param array<array-key, Node|string> $members */
    private static function declaresLiteral(array $members): bool
    {
        $names = array_keys($members);
        return (
            implode('|', $names) === 'null'
            || array_any($names, static fn(int|string $name): bool => PhpDocType::literalSyntax((string) $name))
        );
    }

    /**
     * Compiles the native declaration of a field whose PHPDoc names an unrecognized type, rejecting a literal that does
     * not resolve to a value. Errors of the native declaration come first.
     *
     * @param list<PhpDocType> $declared
     * @throws ReflectionException
     */
    private static function native(
        TypeCompiler $types,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionType $type,
        array $declared,
        PhpDocItemCompiler $items,
    ): Node|DecodeError {
        $native = $types->declared($field, $type);
        foreach ($declared as $member) {
            $unresolved = PhpDocType::literalSyntax($member->name) && $items->literals($member) === null;
            if ($unresolved && !$native instanceof DecodeError) {
                return UnsupportedField::literal($field->getName(), $member->name);
            }
        }
        return $native;
    }
}
