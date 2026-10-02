<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\AbstractClassMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\InterfaceMapField;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;

/** @internal */
final class NonInstantiableMapValueErrorCases
{
    /**
     * @api Called by DecodeErrorCases.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function errors(): iterable
    {
        foreach ([
            'interface' => [new InterfaceMapField([]), RootTargetInterface::class],
            'abstract class' => [new AbstractClassMapField([]), AbstractRootTarget::class],
        ] as $typeKind => [$target, $valueType]) {
            $class = $target::class;
            $message =
                'Could not create '
                . $class
                . ' from the JSON object: Field values uses '
                . $typeKind
                . ' '
                . $valueType
                . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';

            yield $typeKind . ' map value type, member present' => ['{"values":{}}', $class, $message, 3];
            yield $typeKind . ' map value type, member absent' => ['{}', $class, $message, 3];
        }
    }
}
