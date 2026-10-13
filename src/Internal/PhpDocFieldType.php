<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\UnsupportedCollection;
use ReflectionParameter;
use ReflectionProperty;

use function str_contains;

/** @internal */
final class PhpDocFieldType
{
    /** Returns the error for an `array` or `ArrayObject` field without a supported PHPDoc collection declaration. */
    public static function unrecognized(ReflectionParameter|ReflectionProperty $field): DecodeError
    {
        return UnsupportedCollection::unrecognized(
            $field->getName(),
            $field instanceof ReflectionParameter ? 'param' : 'var',
        );
    }

    public static function resolve(ReflectionParameter|ReflectionProperty $field): PhpDocType|null
    {
        $doc = FieldTypeNameResolver::docComment($field);
        $parameter = $field instanceof ReflectionParameter;
        if (!str_contains($doc, $parameter ? 'param' : 'var')) {
            return null;
        }
        $tags = PhpDocTagParser::tags($doc);
        $type = $parameter
            ? PhpDocTagSelector::parameter($tags, $field->getName())
            : PhpDocTagSelector::property($tags, $field->getName());
        return PhpDocTypeTree::limit($type);
    }
}
