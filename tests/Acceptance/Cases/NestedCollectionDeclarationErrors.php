<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\Coordinates;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonFinalSelfCollection;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

/** @internal */
final class NestedCollectionDeclarationErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach ([
            '\\' . RootTargetInterface::class =>
                'interface '
                    . RootTargetInterface::class
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            '\\' . AbstractRootTarget::class =>
                'abstract class '
                    . AbstractRootTarget::class
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            '\\' . NonFinalSelfCollection::class =>
                'non-final class '
                    . NonFinalSelfCollection::class
                    . '. Values may be subclasses, whose runtime class JSON does not identify.',
            '\\' . NonBackedStatus::class =>
                'non-backed enum '
                    . NonBackedStatus::class
                    . ', which cannot be decoded because it has no backing value.',
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
        ] as $leaf => $reason) {
            foreach (['list', 'non-empty-array', 'ArrayObject'] as $inner) {
                $type = 'list<' . NestedCollectionInputs::declaration($inner, $leaf) . '|null>';
                foreach (['param', 'var'] as $tag) {
                    $class = CollectionDeclarationFixture::create('array', $type, $tag);
                    foreach (['{}', '{"value":[]}', '{"value":[null]}'] as $json) {
                        yield 'nested ambiguous declaration ' . $type . $tag . $json => [
                            $json,
                            $class,
                            'Could not create ' . $class . ' from the JSON object: Field value uses ' . $reason,
                            3,
                        ];
                    }
                }
            }
        }
    }
}
