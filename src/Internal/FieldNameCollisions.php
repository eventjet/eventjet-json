<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionProperty;

use function sprintf;

/** @internal */
final class FieldNameCollisions
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array<string, string> $names
     */
    public static function validate(ReflectionClass $class, array $names): DecodeError|null
    {
        $owners = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (($property->getModifiers() & ReflectionProperty::IS_STATIC) !== 0) {
                continue;
            }
            $name = $property->getName();
            $wireName = $names[$name] ?? $name;
            $owner = $owners[$wireName] ?? null;
            if ($owner !== null) {
                return DecodeError::nonInstantiableTarget($class->getName(), sprintf(
                    'Properties %s and %s use the same JSON field name "%s".',
                    $owner,
                    $name,
                    $wireName,
                ));
            }
            $owners[$wireName] = $name;
        }
        return null;
    }
}
