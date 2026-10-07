<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

use function is_string;
use function str_replace;

/** @internal */
final class CollectionUnionRegressionErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (self::inputs() as $index => [$native, $declaration, $documents, $reason]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                $message = match (true) {
                    is_string($reason) => $reason,
                    $tag === 'param' => $reason['param'],
                    default => $reason['var'],
                };
                foreach ($documents as $json) {
                    yield 'collection union regression ' . $index . $tag . $json => [
                        $json,
                        $class,
                        'Could not create ' . $class . ' from the JSON object: ' . $message,
                        3,
                    ];
                }
            }
        }
    }

    /** @return list<array{string, string, list<string>, string|array{param: string, var: string}}> */
    private static function inputs(): array
    {
        $interface = RootTargetInterface::class;
        $enum = StringBackedStatus::class;
        $unsupportedInterface =
            'Field value uses interface '
            . $interface
            . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';
        return [
            [
                'array|string',
                'array{0: int, 1?: string}|string',
                ['{"value":false}'],
                'Field value must be of type array{0: int, 1?: string}|string, bool given.',
            ],
            [
                'array|string',
                'array{int|string, 1?: bool}|string',
                ['{"value":false}'],
                'Field value must be of type array{0: int|string, 1?: bool}|string, bool given.',
            ],
            [
                'array|bool',
                'array{}|bool',
                ['{"value":{}}'],
                'Field value must be of type array{}|bool, stdClass given.',
            ],
            [
                'array|string',
                'array{\\' . $interface . '}|string',
                ['{}', '{"value":[]}'],
                str_replace('value uses', replace: 'value[0] uses', subject: $unsupportedInterface),
            ],
            [
                'array|(\\' . $interface . '&\\Countable)',
                'list<int>|\\' . $interface,
                ['{}', '{"value":[]}'],
                [
                    'param' =>
                        'Field value uses unsupported intersection type '
                            . $interface
                            . '&Countable. JSON does not identify a concrete class to instantiate.',
                    'var' =>
                        'Field value uses unsupported public property type ('
                            . $interface
                            . '&Countable)|array. Public properties outside the constructor support declared scalar, array, backed enum, and final class types, including unions that follow the constructor-field rules.',
                ],
            ],
            ['array', 'list<int|list<\\' . $interface . '>>', ['{}', '{"value":[]}'], $unsupportedInterface],
            [
                'array',
                'list<null|list<int>>',
                ['{"value":[false]}'],
                'Field value[0] must be of type list<int>, bool given.',
            ],
            [
                'array',
                'list<string|\\' . $enum . '|list<int>|list<string>>',
                ['{}', '{"value":[]}'],
                'Field value uses backed enum '
                    . $enum
                    . ' together with its backing type string. JSON cannot distinguish an enum case from the scalar value.',
            ],
        ];
    }
}
