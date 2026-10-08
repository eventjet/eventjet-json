<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

/** @internal */
final class CollectionNameTypeErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            [$class, $type] = CollectionNameDeclaration::source(
                ['', 'namespace\\LocalPerson', null],
                $tag,
                'list',
                'global',
                scenario: 'namespace-relative-type-error',
            );
            yield 'global namespace-relative mismatch/' . $tag => [
                '{"value":[42]}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value[0] must be of type '
                    . $type
                    . ', int given.',
                3,
            ];
            $interface = 'Eventjet\\Json\\Test\\Acceptance\\Fixtures\\RootTargetInterface';
            [$class] = CollectionNameDeclaration::source(
                ['use \\' . $interface . ' as Item;', 'Item', null],
                $tag,
                'list',
                'semicolon',
            );
            yield 'fully qualified interface import/' . $tag => [
                '{}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value uses interface '
                    . $interface
                    . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
                3,
            ];
        }
    }
}
