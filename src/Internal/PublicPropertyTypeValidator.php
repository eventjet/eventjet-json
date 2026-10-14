<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedField;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;

/** @internal */
final class PublicPropertyTypeValidator
{
    /** @throws ReflectionException */
    public static function validate(ReflectionProperty $property): Node|DecodeError
    {
        $field = $property->getName();
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType && !$type instanceof ReflectionUnionType) {
            return UnsupportedField::publicProperty($field, $type === null ? 'none' : (string) $type);
        }

        foreach ($type instanceof ReflectionNamedType ? [$type] : $type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return UnsupportedField::publicProperty($field, (string) $type);
            }

            $typeName = FieldTypeNameResolver::resolve($property, $member);
            $typeIsSupported = self::isSupportedType($member, $typeName);

            if (!$typeIsSupported) {
                return UnsupportedField::publicProperty($field, (string) $type);
            }
        }

        return new TypeCompiler()->field($property, $type);
    }

    private static function isSupportedType(ReflectionNamedType $member, string $type): bool
    {
        if (!$member->isBuiltin()) {
            return $type !== stdClass::class && (enum_exists($type) || class_exists($type) || interface_exists($type));
        }

        return in_array(
            $type,
            [
                'array',
                'bool',
                'false',
                'float',
                'int',
                'null',
                'string',
                'true',
            ],
            strict: true,
        );
    }
}
