<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\AbstractClassListField;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\InterfaceListField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonFinalClassListField;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;

/** @internal */
final class NonInstantiableListItemErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'interface' => [new InterfaceListField([]), RootTargetInterface::class],
            'abstract class' => [new AbstractClassListField([]), AbstractRootTarget::class],
            'non-final class' => [new NonFinalClassListField([]), ParentClassFieldBase::class],
        ] as $typeKind => [$target, $itemType]) {
            $class = $target::class;
            $message =
                'Could not create '
                . $class
                . ' from the JSON object: Field values uses '
                . $typeKind
                . ' '
                . $itemType
                . (
                    $typeKind === 'non-final class'
                        ? '. Values may be subclasses, whose runtime class JSON does not identify.'
                        : ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.'
                );

            yield $typeKind . ' list item type, member present' => ['{"values":[]}', $class, $message, 3];
            yield $typeKind . ' list item type, member absent' => ['{}', $class, $message, 3];
        }
    }
}
