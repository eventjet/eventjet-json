<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

/** @internal */
final class CollectionUnionDeclarationErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            '\\' . Coordinates::class . '|\\' . Person::class . '|\\' . StringBackedStatus::class . '|string' =>
                'multiple class types: '
                    . Coordinates::class
                    . ', '
                    . Person::class
                    . '. JSON does not identify which class to instantiate.',
            '\\' . OverlappingStringBackedStatus::class . '|\\' . StringBackedStatus::class =>
                'multiple backed enums '
                    . OverlappingStringBackedStatus::class
                    . ' and '
                    . StringBackedStatus::class
                    . " with overlapping backing value 'ready'. JSON cannot identify which enum case to instantiate.",
            '\\' . Coordinates::class . '|\\' . Person::class =>
                'multiple class types: '
                    . Coordinates::class
                    . ', '
                    . Person::class
                    . '. JSON does not identify which class to instantiate.',
            '\\' . StringBackedStatus::class . '|string' =>
                'backed enum '
                    . StringBackedStatus::class
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
        ] as $type => $detail) {
            foreach (CollectionUnionCases::shapes($type) as $shape => [$native, $declaration]) {
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                    foreach (['{}', '{"value":[]}', '{"value":null}'] as $json) {
                        $path = $shape === 'tuple' || $shape === 'optional tuple' ? 'value[0]' : 'value';
                        yield 'ambiguous collection union ' . $declaration . $tag . $json => [
                            $json,
                            $class,
                            'Could not create ' . $class . ' from the JSON object: Field ' . $path . ' uses ' . $detail,
                            3,
                        ];
                    }
                }
            }
        }
    }
}
