<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Fixtures;

use Override;
use ReflectionClass;
use ReflectionProperty;

/**
 * @internal
 * @template T of object
 * @extends ReflectionClass<T>
 */
final class PropertyCountingReflection extends ReflectionClass
{
    public int $propertyLookups = 0;
    public int $parentLookups = 0;

    /**
     * @return ReflectionClass<object>|false
     * @psalm-external-mutation-free
     */
    #[Override]
    public function getParentClass(): ReflectionClass|false
    {
        ++$this->parentLookups;
        return parent::getParentClass();
    }

    /**
     * @return list<ReflectionProperty>
     * @psalm-external-mutation-free
     */
    #[Override]
    public function getProperties(int|null $filter = null): array
    {
        if ($filter !== null) {
            return parent::getProperties(ReflectionProperty::IS_PUBLIC);
        }
        ++$this->propertyLookups;
        return parent::getProperties();
    }
}
