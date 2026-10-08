<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use JsonException;
use ReflectionException;
use RuntimeException;

/** @internal */
final class TypeValidationCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{0: class-string|JsonType<mixed>, 1?: string}>
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws JsonException
     */
    public static function declarations(): iterable
    {
        yield from self::valid();
        yield from self::invalid();
    }

    /**
     * @return iterable<string, array{class-string|JsonType<mixed>}>
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws JsonException
     */
    private static function valid(): iterable
    {
        foreach (ObjectRoundTripCases::objects() as $label => [$object]) {
            yield $label => [$object::class];
        }
        foreach (RootCollectionRoundTripCases::objects() as $label => [1 => $type]) {
            yield $label => [$type()];
        }
        foreach (SupportedDocumentRoundTripCases::objects() as $label => [1 => $class]) {
            yield $label => [$class];
        }
        yield 'constructors are never called' => [CollectionNameSource::load(
            'ValidationThrowingConstructor',
            'final class ValidationThrowingConstructor { public function __construct(public int $value) { throw new RuntimeException("must not run"); } }',
        )];
        yield 'mutually recursive declarations' => [CollectionNameSource::load(
            'ValidationCycleA',
            'final class ValidationCycleA { public ValidationCycleB $child; } final class ValidationCycleB { public ValidationCycleA $parent; }',
        )];
    }

    /**
     * @return iterable<string, array{class-string|JsonType<mixed>, string}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function invalid(): iterable
    {
        foreach ([
            FieldDeclarationErrorCases::errors(),
            ConstructionErrorCases::declarations(),
            TupleDeclarationErrorCases::errors(),
            UnsupportedTargetErrorCases::errors(),
            UnsupportedUnionErrorCases::errors(),
            AmbiguousUnionErrorCases::errors(),
        ] as $cases) {
            foreach ($cases as $label => [1 => $class, 2 => $message]) {
                yield $label => [$class, $message];
            }
        }
        $unknown = CollectionNameSource::load(
            'ValidationUnknownChild',
            'final class ValidationUnknownChild { public function __construct(public ?ValidationMissingChild $child = null) {} }',
        );
        yield 'unknown nested class' => [
            $unknown,
            DecodeError::nonInstantiableField(
                $unknown,
                'child',
                'unknown class',
                'ValidationMissingChild',
            )->getMessage(),
        ];
        yield from self::nested();
    }

    /**
     * @return iterable<string, array{class-string|JsonType<mixed>, string}>
     * @throws RuntimeException
     */
    private static function nested(): iterable
    {
        $invalid = CollectionNameSource::load(
            'ValidationInvalidChild',
            'final class ValidationInvalidChild { public function __construct(public mixed $value) {} }',
        );
        $message = DecodeError::nonInstantiableField(
            $invalid,
            'value',
            'unsupported type',
            'mixed',
            '. The declaration does not provide enough type information to preserve PHP value types and JSON shapes during a round trip.',
        )->getMessage();
        foreach ([
            ['ValidationNullableChild', 'public function __construct(public ?ValidationInvalidChild $child = null) {}'],
            ['ValidationUnionChild', 'public ValidationInvalidChild|string $child;'],
            ['ValidationEmptyList', '/** @var list<ValidationInvalidChild> */ public array $children = [];'],
            [
                'ValidationNestedTuple',
                '/** @var array{list<ArrayObject<string, ValidationInvalidChild>>} */ public array $children;',
            ],
            ['ValidationCollectionUnion', '/** @var list<ValidationInvalidChild|string> */ public array $children;'],
            [
                'ValidationNestedCollectionUnion',
                '/** @var list<list<ValidationInvalidChild>|string> */ public array $children;',
            ],
            [
                'ValidationLaterTupleClass',
                '/** @var array{list<int>, ValidationInvalidChild} */ public array $children;',
            ],
            [
                'ValidationLaterUnionTupleClass',
                '/** @var array{int|string, ValidationInvalidChild} */ public array $children;',
            ],
            ['ValidationCycleError', 'public ?self $next = null; public ValidationInvalidChild $child;'],
        ] as [$name, $body]) {
            yield $name => [CollectionNameSource::load($name, 'final class ' . $name . ' { ' . $body . ' }'), $message];
        }
        yield 'invalid root list item' => [JsonType::array($invalid), $message];
        yield 'invalid root map item' => [JsonType::map(JsonType::array($invalid)), $message];
    }
}
