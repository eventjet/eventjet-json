<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

final class PublicPropertiesWithConstructor extends InheritedPublicProperty
{
    public string $label = '';
    public bool $active = false;

    public function __construct(
        public readonly string $constructorBound,
    ) {}
}
