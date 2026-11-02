<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayIterator;
use DatePeriod;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\MixedField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;

/** @internal */
final class FieldDeclarationErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        yield 'untyped constructor field is rejected even when absent' => [
            '{}',
            DatePeriod::class,
            'Could not create DatePeriod from the JSON object: Field start uses no type declaration. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'non-nullable non-backed enum field is rejected even when absent' => [
            '{}',
            CollectionDeclarationFixture::create('\\' . NonBackedStatus::class, '', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('\\' . NonBackedStatus::class, '', 'param')
                . ' from the JSON object: Field value uses non-backed enum Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus, which cannot be decoded because it has no backing value.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'intersection constructor field is rejected even when absent' => [
            '{}',
            new IntersectionTypeField(new ArrayIterator())::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'mixed constructor field is rejected even when absent' => [
            '{}',
            new MixedField(null)::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\MixedField from the JSON object: Field value uses unsupported type mixed. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield from self::imprecise();
        yield 'intersection public property is unsupported' => [
            '{"value":{}}',
            new IntersectionPublicProperty()::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\IntersectionPublicProperty from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        yield 'public property of an unknown class rejects an object' => [
            '{"value":{}}',
            CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var'),
            'Could not create '
                . CollectionDeclarationFixture::create('UnknownPublicPropertyType', '', 'var')
                . ' from the JSON object: Field value uses unknown class UnknownPublicPropertyType, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        $resource = CollectionDeclarationFixture::create('\resource', '', 'var');
        yield 'native class named resource is an unknown class' => [
            '{"value":{}}',
            $resource,
            'Could not create '
                . $resource
                . ' from the JSON object: Field value uses unknown class resource, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            DecodeErrorKind::UnsupportedDeclaration,
        ];
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    private static function imprecise(): iterable
    {
        $callable = CollectionNameSource::load(
            'CallableParameter',
            'final class CallableParameter { public $value; public function __construct(callable $value) { $this->value = $value; } }',
        );
        $callableUnion = CollectionNameSource::load(
            'CallableUnionParameter',
            'final class CallableUnionParameter { public int|string $value; public function __construct(callable|int $value) { $this->value = is_int($value) ? $value : "callable"; } }',
        );
        foreach ([
            'iterable constructor field' => [CollectionDeclarationFixture::create('iterable', '', 'param'), 'iterable'],
            'iterable constructor field with a list declaration' => [
                CollectionDeclarationFixture::create('iterable', 'list<int>', 'param'),
                'iterable',
            ],
            'nullable iterable public property' => [
                CollectionDeclarationFixture::create('?iterable', '', 'var'),
                'iterable',
            ],
            'callable constructor field' => [$callable, 'callable'],
            'callable union constructor field' => [$callableUnion, 'callable'],
        ] as $label => [$class, $type]) {
            $message =
                'Could not create '
                . $class
                . ' from the JSON object: Field value uses unsupported type '
                . $type
                . '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.';
            yield $label . ' is rejected even when absent' => [
                '{}',
                $class,
                $message,
                DecodeErrorKind::UnsupportedDeclaration,
            ];
            yield $label . ' is rejected before PHP sees a value' => [
                '{"value":"s"}',
                $class,
                $message,
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
    }
}
