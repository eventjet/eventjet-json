<?php

declare(strict_types=1);

namespace FieldPrototype;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

final readonly class Reference implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(#[Field('$ref')] public string $ref) {}
}

final readonly class MissingInterface
{
    public function __construct(#[Field('$ref')] public string $ref = 'default') {}
}

final readonly class SameNameMissingInterface
{
    public function __construct(#[Field('ref')] public string $ref = 'default') {}
}

final readonly class ManualReference implements JsonSerializable
{
    public function __construct(#[Field('$ref')] public string $ref) {}

    public function jsonSerialize(): object
    {
        return (object) ['$ref' => $this->ref];
    }
}

final readonly class BrokenReference implements JsonSerializable
{
    public function __construct(#[Field('$ref')] public string $ref) {}

    public function jsonSerialize(): object
    {
        return (object) ['wrong' => $this->ref];
    }
}

final readonly class UnmappedSerializer implements JsonSerializable
{
    public function __construct(public string $ref) {}

    public function jsonSerialize(): string
    {
        return $this->ref;
    }
}

final readonly class Document implements JsonSerializable
{
    use MappedJsonFields;

    /** @param list<Reference> $references */
    public function __construct(
        #[Field('$id')] public string $id,
        #[Field('links')] public array $references,
    ) {}
}

class InheritedFields
{
    #[Field('$anchor')]
    public string $anchor = 'original';
}

final class Properties extends InheritedFields implements JsonSerializable
{
    use MappedJsonFields;

    #[Field('$ref')]
    public string|null $ref = 'original';
}

final readonly class Collision implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        #[Field('name')] public string $ref = '',
        public string $name = '',
    ) {}
}

final readonly class SpecialNames implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        #[Field('0')] public string $zero,
        #[Field('')] public string $empty,
        #[Field('second')] public string $first,
        #[Field('first')] public string $second,
    ) {}
}

final readonly class Plain
{
    public function __construct(public string $ref, public int $count, public bool $ready) {}
}

final readonly class Mapped implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        #[Field('uri')] public string $ref,
        #[Field('total')] public int $count,
        #[Field('valid')] public bool $ready,
    ) {}
}

final readonly class Defaults implements JsonSerializable
{
    use MappedJsonFields;

    public function __construct(
        public string $title = 'default',
        #[Field('$ref')] public string|null $ref = 'original',
        public int $count = 7,
    ) {}
}
