<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function preg_match;

/** @internal */
final class PhpDocLiteralFieldValidator
{
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
            $literalSyntax = preg_match('/\A[\x27"0-9.+-]|::/', $member->name) === 1;
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
