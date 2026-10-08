<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
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
    /**
     * @param class-string $class
     * @return array{type: ReflectionNamedType|ReflectionUnionType, collection: ListType|MapType|TupleType|FieldCollectionUnionType|null, typeName: string}|DecodeError
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionProperty $property): array|DecodeError
    {
        $field = $property->getName();
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType && !$type instanceof ReflectionUnionType) {
            return self::unsupportedType($class, $field, $type === null ? 'none' : (string) $type);
        }

        foreach ($type instanceof ReflectionNamedType ? [$type] : $type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return self::unsupportedType($class, $field, (string) $type);
            }

            $typeName = FieldTypeNameResolver::resolve($property, $member);
            $typeIsSupported = self::isSupportedType($member, $typeName);

            if (!$typeIsSupported) {
                return self::unsupportedType($class, $field, (string) $type);
            }
        }

        $collection = FieldTypeResolver::resolve($class, $property);

        if ($collection instanceof DecodeError) {
            return $collection;
        }

        return [
            'type' => $type,
            'collection' => $collection,
            'typeName' => $type instanceof ReflectionNamedType
                ? FieldTypeNameResolver::resolve($property, $type)
                : (string) $type,
        ];
    }

    /** @param class-string $class */
    private static function unsupportedType(string $class, string $field, string $type): DecodeError
    {
        return DecodeError::nonInstantiableField(
            $class,
            $field,
            'unsupported public property type',
            $type,
            '. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
        );
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
