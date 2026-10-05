<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\ParentClassFieldBase;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use RuntimeException;

/** @internal */
final class TupleDeclarationErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            foreach (self::unsupportedTypes() as $type => $detail) {
                foreach (['array{int, \\' . $type . '}', 'array{0: int, 1?: \\' . $type . '}'] as $declaration) {
                    $class = CollectionDeclarationFixture::create('array', $declaration, $tag);

                    foreach (['{}', '{"value":[]}', '{"value":[1,{}]}'] as $json) {
                        yield 'tuple unsupported ' . $tag . $declaration . $json => [
                            $json,
                            $class,
                            'Could not create ' . $class . ' from the JSON object: Field value[1] uses ' . $detail,
                            3,
                        ];
                    }
                }
            }
        }
    }

    /** @return iterable<class-string, string> */
    private static function unsupportedTypes(): iterable
    {
        $notInstantiable = ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';
        yield RootTargetInterface::class => 'interface ' . RootTargetInterface::class . $notInstantiable;
        yield AbstractRootTarget::class => 'abstract class ' . AbstractRootTarget::class . $notInstantiable;
        yield ParentClassFieldBase::class =>
            'non-final class '
                . ParentClassFieldBase::class
                . '. Values may be subclasses, whose runtime class JSON does not identify.';
        yield NonBackedStatus::class =>
            'non-backed enum ' . NonBackedStatus::class . ', which cannot be decoded because it has no backing value.';
    }
}
