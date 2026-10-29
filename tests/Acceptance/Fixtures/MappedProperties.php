<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use Eventjet\Json\Field;
use Eventjet\Json\MappedJsonFields;
use JsonSerializable;

/** @internal */
final class MappedProperties extends MappedPropertyBase implements JsonSerializable
{
    use MappedJsonFields;

    #[Field('$ref')]
    public string|null $ref = 'original';

    #[Field('pending')]
    public string $uninitialized = '';

    public function __construct()
    {
        unset($this->uninitialized);
    }

    /** @var list<MappedReference> */
    #[Field('links')]
    public array $references = [];
}
