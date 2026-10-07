<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedOutcome;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\OverlappingStringBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\SerializableNonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

/** @internal */
final class NonEncodableUnionErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::rejectedUnions() as $union => $detail) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($union, '', $tag);
                foreach (['{}', '{"value":null}'] as $json) {
                    yield 'rejected native non-encodable union ' . $union . $tag . $json => [
                        $json,
                        $class,
                        'Could not create ' . $class . ' from the JSON object: Field value uses ' . $detail,
                        3,
                    ];
                }
                foreach (CollectionUnionCases::shapes($union) as $shape => [$native, $declaration]) {
                    $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                    $path = $shape === 'tuple' || $shape === 'optional tuple' ? 'value[0]' : 'value';
                    foreach (['{}', '{"value":[]}', '{"value":{}}'] as $json) {
                        yield 'rejected collection non-encodable union ' . $declaration . $tag . $json => [
                            $json,
                            $class,
                            'Could not create ' . $class . ' from the JSON object: Field ' . $path . ' uses ' . $detail,
                            3,
                        ];
                    }
                }
            }
        }
        yield from NonEncodableUnionBoundaryErrors::errors();
    }

    /** @return iterable<string, string> */
    private static function rejectedUnions(): iterable
    {
        $enum = '\\' . NonBackedStatus::class;
        yield $enum . '|\\' . NonBackedOutcome::class =>
            'non-backed enum ' . NonBackedStatus::class . ', which cannot be decoded because it has no backing value.';
        yield $enum . '|\\' . StringBackedStatus::class . '|string' =>
            'backed enum '
                . StringBackedStatus::class
                . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.';
        yield $enum . '|\\' . OverlappingStringBackedStatus::class . '|\\' . StringBackedStatus::class =>
            'multiple backed enums '
                . OverlappingStringBackedStatus::class
                . ' and '
                . StringBackedStatus::class
                . " with overlapping backing value 'ready'. JSON cannot identify which enum case to instantiate.";
        yield $enum . '|\\' . Coordinates::class . '|\\' . Person::class =>
            'multiple class types: '
                . Coordinates::class
                . ', '
                . Person::class
                . '. JSON does not identify which class to instantiate.';
        yield $enum . '|\\' . RootTargetInterface::class . '|int' =>
            'interface '
                . RootTargetInterface::class
                . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';
        yield '\\' . SerializableNonBackedStatus::class . '|int' =>
            'non-backed enum '
                . SerializableNonBackedStatus::class
                . ', which cannot be decoded because it has no backing value.';
        yield '\\' . SerializableNonBackedStatus::class . '|null' =>
            'non-backed enum '
                . SerializableNonBackedStatus::class
                . ', which cannot be decoded because it has no backing value.';
    }
}
