<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_any;
use function in_array;
use function strtolower;

/**
 * Compiles a native union declaration such as `Person|string|null`.
 *
 * @internal
 */
final class NativeUnionCompiler
{
    /** @throws ReflectionException */
    public static function compile(
        TypeCompiler $types,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
    ): Node|DecodeError {
        $members = $type->getTypes();
        $hasCollection = array_any(
            $members,
            static fn(ReflectionType $member): bool => $member instanceof ReflectionNamedType
            && in_array(strtolower($member->getName()), ['array', 'arrayobject'], strict: true),
        );
        if ($hasCollection) {
            return new CollectionUnionCompiler($types, $field)->compile($type);
        }
        $union = new UnionBuilder($types->class, $field->getName());
        return self::addMembers($types, $field, $members, $union) ?? $union->build((string) $type);
    }

    /**
     * Adds the native members of a union, except collections, whose items only PHPDoc declares.
     *
     * @param array<ReflectionType> $members
     * @throws ReflectionException
     */
    public static function addMembers(
        TypeCompiler $types,
        ReflectionParameter|ReflectionProperty $field,
        array $members,
        UnionBuilder $union,
    ): DecodeError|null {
        $path = $field->getName();
        foreach ($members as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return DecodeError::unsupportedIntersection($types->class, $path, (string) $member);
            }
            $name = FieldTypeNameResolver::resolve($field, $member);
            $nonEncodable = EnumCompiler::isNonEncodable($name);
            if ($nonEncodable) {
                $union->reject(EnumCompiler::nonEncodable($types->class, $name, $path));
                continue;
            }
            if ($name === 'array' || $name === ArrayObject::class) {
                continue;
            }
            $node = $types->named($field, $member);
            if ($node instanceof DecodeError) {
                return $node;
            }
            $union->add($node);
        }
        return null;
    }
}
