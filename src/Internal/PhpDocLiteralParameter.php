<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Override;
use ReflectionException;

/** @internal */
final readonly class PhpDocLiteralParameter extends ConstructorParameter
{
    /** @throws ReflectionException */
    #[Override]
    public function converter(ListType|MapType|TupleType|CollectionUnionType|false|null $resolved): FieldValueConverter|null
    {
        $literal = PhpDocLiteralFieldType::accepts($this->type);
        return $literal
            ? PhpDocLiteralFieldType::converter($this->reflection, $resolved === false ? null : $resolved)
            : parent::converter($resolved);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    #[Override]
    protected function declaration(string $class): ListType|MapType|TupleType|CollectionUnionType|DecodeError|false|null
    {
        $literal = PhpDocLiteralFieldType::accepts($this->type);
        return $literal ? PhpDocLiteralFieldType::resolve($class, $this->reflection) : parent::declaration($class);
    }
}
