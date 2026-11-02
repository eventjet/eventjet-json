<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function is_int;
use function is_string;

/**
 * Reads the discriminator values that a field declares. It reads only the PHPDoc of the field and compiles no class, so
 * that classes that contain a union, directly or through other classes, do not compile while its discriminator is
 * inferred.
 *
 * @internal
 */
final class DiscriminatorValues
{
    /**
     * Returns the JSON values of a field whose type is a union of string, integer, and backed enum case literals.
     *
     * @param ReflectionClass<object> $class
     * @return non-empty-list<int|string>|null
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function of(ReflectionClass $class, ReflectionParameter|ReflectionProperty $field): array|null
    {
        try {
            $doc = self::phpDoc($field);
        } catch (DecodeError $error) {
            throw $error->whileCreating($class->getName());
        }
        $values = [];
        foreach ($doc?->members() ?? [] as $member) {
            $value = $member->literal?->value;
            $json = $value instanceof BackedEnum ? $value->value : $value;
            if (!is_int($json) && !is_string($json)) {
                return null;
            }
            $values[] = $json;
        }
        return $values === [] ? null : $values;
    }

    /**
     * Returns the PHPDoc type of a field with resolved names, if it narrows the native type.
     *
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function phpDoc(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $type = $field->getType();
        $declared = PhpDocFieldType::resolve($field);
        $resolved = $declared === null ? null : new PhpDocTypeResolver($field)->resolve($declared);
        return $resolved === null || $type === null ? null : TypeCompiler::narrowing($resolved, $field, $type);
    }
}
