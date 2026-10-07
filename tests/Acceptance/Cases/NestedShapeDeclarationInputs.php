<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;

/** @internal */
final class NestedShapeDeclarationInputs
{
    /** @return iterable<array{string, string, list<string>}> */
    public static function declarations(string $tag): iterable
    {
        foreach ([
            'list<array{1: int}>',
            'list<array{0: int, 2?: string}>',
            'array{0?: array{0?: int, 1: string}}',
            'list<array{name: string}>',
            'array{list<array{int, ...}>}',
            'array{list<UnknownItem>}',
            'array{array<string, int>}',
            'list<array{list<array<string, int>>}>',
            'list<array{0: int}|null<string>>',
        ] as $type) {
            yield [
                $type,
                'Field value has a missing or unrecognized collection declaration. Use @'
                    . $tag
                    . ' with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.',
                ['{}', '{"value":[]}'],
            ];
        }
        foreach ([
            'list<array{int}|list<int>>' => 'value',
            'list<array{int}|array{string}>' => 'value',
            'array{array{int}|list<int>}' => 'value[0]',
            'array{ArrayObject<string, int>|non-empty-array<string, int>}' => 'value[0]',
            'array{list<array{int}|array{string}>}' => 'value[0]',
        ] as $type => $path) {
            yield [
                $type,
                'Field '
                    . $path
                    . ' has multiple union members with the same JSON shape. JSON cannot identify which type to restore.',
                ['{}', '{"value":[]}'],
            ];
        }
        yield [
            'list<array{0?: array{0?: \\' . RootTargetInterface::class . '}}|null>',
            'Field value[0][0] uses interface '
                . RootTargetInterface::class
                . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            ['{}', '{"value":[]}', '{"value":[null]}'],
        ];

        yield from self::unsupportedLeaves();
    }

    /** @return iterable<array{string, string, list<string>}> */
    private static function unsupportedLeaves(): iterable
    {
        $notInstantiable = ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';
        foreach ([
            '\\' . RootTargetInterface::class => 'interface ' . RootTargetInterface::class . $notInstantiable,
            '\\' . AbstractRootTarget::class => 'abstract class ' . AbstractRootTarget::class . $notInstantiable,
            '\\' . ParentClassFieldBase::class =>
                'non-final class '
                    . ParentClassFieldBase::class
                    . '. Values may be subclasses, whose runtime class JSON does not identify.',
            '\\' . NonBackedStatus::class =>
                'non-backed enum '
                    . NonBackedStatus::class
                    . ', which cannot be decoded because it has no backing value.',
            '\\' . StringBackedStatus::class . '|string' =>
                'backed enum '
                    . StringBackedStatus::class
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
        ] as $leaf => $reason) {
            foreach ([
                'list<array{0?: ' . $leaf . '}>' => 'value[0]',
                'array{0?: list<' . $leaf . '>|null}' => 'value[0]',
            ] as $type => $path) {
                yield [$type, 'Field ' . $path . ' uses ' . $reason, ['{}', '{"value":[]}', '{"value":[null]}']];
            }
        }
    }
}
