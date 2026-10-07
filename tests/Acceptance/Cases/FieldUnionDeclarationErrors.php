<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\NonFinalSelfCollection;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

/** @internal */
final class FieldUnionDeclarationErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::types() as $name => $reason) {
            foreach (['param', 'var'] as $tag) {
                foreach ([
                    'array|string' => 'list<\\' . $name . '>|string',
                    'array|\\' . $name => 'list<int>|\\' . $name,
                ] as $native => $declaration) {
                    $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                    foreach (['{}', '{"value":[]}'] as $json) {
                        yield 'unsupported collection union ' . $native . $declaration . $tag . $json => [
                            $json,
                            $class,
                            'Could not create ' . $class . ' from the JSON object: Field value uses ' . $reason,
                            3,
                        ];
                    }
                }
            }
        }
        $enum = StringBackedStatus::class;
        foreach (['param', 'var'] as $tag) {
            $class = CollectionDeclarationFixture::create(
                'array|\\' . $enum . '|string',
                'list<int>|\\' . $enum . '|string',
                $tag,
            );
            yield 'ambiguous collection enum backing union ' . $tag => [
                '{}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses backed enum '
                    . $enum
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
                3,
            ];
        }
    }

    /** @return array<class-string, string> */
    private static function types(): array
    {
        return [
            RootTargetInterface::class =>
                'interface '
                    . RootTargetInterface::class
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            AbstractRootTarget::class =>
                'abstract class '
                    . AbstractRootTarget::class
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            NonFinalSelfCollection::class =>
                'non-final class '
                    . NonFinalSelfCollection::class
                    . '. Values may be subclasses, whose runtime class JSON does not identify.',
            NonBackedStatus::class =>
                'non-backed enum '
                    . NonBackedStatus::class
                    . ', which cannot be decoded because it has no backing value.',
        ];
    }
}
