<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use JsonSerializable;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_any;
use function enum_exists;
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
            $nonEncodable = self::isNonEncodable($name);
            if ($nonEncodable) {
                $union->reject(self::nonEncodable($types->class, $name, $path));
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

    /**
     * Whether values of a type have no JSON representation. Unions skip such members if others remain.
     *
     * @throws ReflectionException
     */
    public static function isNonEncodable(string $type): bool
    {
        if (in_array($type, ['resource', 'open-resource', 'closed-resource'], strict: true)) {
            return true;
        }
        if (!enum_exists($type)) {
            return false;
        }
        $enum = new ReflectionEnum($type);
        return !$enum->isBacked() && !$enum->implementsInterface(JsonSerializable::class);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function nonEncodable(string $class, string $type, string $path): DecodeError
    {
        if (enum_exists($type)) {
            return DecodeError::nonBackedEnum($class, $type, $path);
        }
        return DecodeError::nonInstantiableField(
            $class,
            $path,
            'non-JSON-encodable type',
            $type,
            ', which has no JSON representation.',
        );
    }
}
