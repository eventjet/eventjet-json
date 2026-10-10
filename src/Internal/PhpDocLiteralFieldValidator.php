<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final class PhpDocLiteralFieldValidator
{
    /**
     * @param class-string $class
     * @param list<string>|null $literals
     * @throws ReflectionException
     */
    public static function validateUnresolved(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        array|null $literals,
    ): DecodeError|null {
        return $literals === null ? self::validate($class, $field) : null;
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function validate(string $class, ReflectionParameter|ReflectionProperty $field): DecodeError|null
    {
        $type = PhpDocFieldType::resolve($field);
        if ($type === null) {
            return null;
        }
        foreach ($type->name === '|' ? $type->arguments : [$type] as $member) {
            $literalSyntax = PhpDocType::literalSyntax($member->name);
            $resolved = $literalSyntax ? PhpDocItemTypeResolver::resolve($field, $member) : null;
            if ($literalSyntax && $resolved === null) {
                return DecodeError::nonInstantiableField(
                    $class,
                    $field->getName(),
                    'unsupported literal declaration',
                    $member->name,
                );
            }
        }
        $names = PhpDocLiteralField::resolve($field);
        return $names === null ? null : PhpDocLiteralUnionValidator::validate($class, $field->getName(), $names);
    }
}
