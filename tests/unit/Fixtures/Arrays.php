<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

use Eventjet\Json\ArrayOf;

final readonly class Arrays
{
    /**
     * @param list<string> $strings
     * @param list<int> $ints
     * @param list<float> $floats
     * @param list<bool> $bools
     * @param list<null> $nulls
     * @param list<RequiredString> $objects
     * @param list<StringBackedEnum> $stringEnums
     * @param list<IntBackedEnum> $intEnums
     * @param list<list<string>> $stringArrays
     */
    public function __construct(
        #[ArrayOf('string')] public array $strings,
        #[ArrayOf('int')] public array $ints,
        #[ArrayOf('float')] public array $floats,
        #[ArrayOf('bool')] public array $bools,
        #[ArrayOf('null')] public array $nulls,
        #[ArrayOf(RequiredString::class)] public array $objects,
        #[ArrayOf(StringBackedEnum::class)] public array $stringEnums,
        #[ArrayOf(IntBackedEnum::class)] public array $intEnums,
        #[ArrayOf(new ArrayOf('string'))] public array $stringArrays,
    ) {
    }
}
