<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Fixtures;

use ArrayObject;

final class ObjectCollectionValidationFields
{
    /** @var list<Person> */
    public array $publicList;

    /** @var non-empty-list<Person> */
    public array $publicNonEmptyList;

    /** @var non-empty-array<string, Person> */
    public array $publicMap;

    /** @var ArrayObject<string, Person> */
    public ArrayObject $publicObjectMap;

    /**
     * @param list<Person> $list
     * @param non-empty-list<Person> $nonEmptyList
     * @param non-empty-array<string, Person> $map
     * @param ArrayObject<string, Person> $objectMap
     */
    public function __construct(
        public array $list = [new Person('Ada', 'Lovelace')],
        public array $nonEmptyList = [new Person('Ada', 'Lovelace')],
        public array $map = ['valid' => new Person('Ada', 'Lovelace')],
        public ArrayObject $objectMap = new ArrayObject(),
    ) {
        $this->publicList = $list;
        $this->publicNonEmptyList = $nonEmptyList;
        $this->publicMap = $map;
        $this->publicObjectMap = $objectMap;
    }
}
