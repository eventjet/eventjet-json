<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\OmittedInitializedReadonlyPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor;
use Eventjet\Json\Test\Acceptance\Fixtures\VariadicConstructorTarget;

/** @internal */
final class ConstructionErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|\Eventjet\Json\JsonType<list<mixed>|object>, 2: string, 3: int, 4?: \Throwable}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['"invalid"', 'false', 'null', '[]', '{}'] as $value) {
            $type = match ($value) {
                '"invalid"' => 'string',
                'false' => 'bool',
                'null' => 'null',
                '[]' => 'array',
                default => 'stdClass',
            };
            yield 'public-property ' . $type . ' input is rejected before construction' => [
                '{"value":"rejected","other":' . $value . '}',
                ThrowingConstructor::class,
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor from the JSON object: Field other must be of type int, '
                    . $type
                    . ' given.',
                DecodeErrorKind::InvalidValue->value,
            ];
        }
        yield 'unloaded class parameter rejects null' => [
            '{"value":null}',
            CollectionDeclarationFixture::create('UnloadedRequiredClass', '', 'param'),
            'Could not create '
                . CollectionDeclarationFixture::create('UnloadedRequiredClass', '', 'param')
                . ' from the JSON object: Field value must be of type UnloadedRequiredClass, null given.',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'constructor throws an exception' => [
            '{"value":"rejected"}',
            new ThrowingConstructor('accepted')::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor from the JSON object: The constructor rejected the decoded value.',
            DecodeErrorKind::ConstructorFailure->value,
            ThrowingConstructor::exception(),
        ];
        yield 'missing constructor arguments' => [
            '{}',
            Person::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Too few arguments to function Eventjet\Json\Test\Acceptance\Fixtures\Person::__construct(), 0 passed and at least 2 expected',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield 'missing constructor argument before a present one' => [
            '{"lastName":"Lovelace"}',
            Person::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\Person from the JSON object: Eventjet\Json\Test\Acceptance\Fixtures\Person::__construct(): Argument #1 ($firstName) not passed',
            DecodeErrorKind::InvalidValue->value,
        ];
        yield from self::targetFailures();
        yield from self::declarations();
    }

    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws \RuntimeException
     */
    private static function targetFailures(): iterable
    {
        yield 'constructor throws while an optional member is omitted' => [
            '{"value":"accepted"}',
            CollectionNameSource::load(
                'OptionalMemberThrowingConstructor',
                'final class OptionalMemberThrowingConstructor { public function __construct(public string $value, public int $count = 0) { throw new ArgumentCountError("The constructor threw."); } }',
            ),
            'Could not create OptionalMemberThrowingConstructor from the JSON object: The constructor threw.',
            DecodeErrorKind::ConstructorFailure->value,
        ];
        yield 'property hook rejects a value' => [
            '{"value":"rejected"}',
            CollectionNameSource::load(
                'RejectingPropertyHook',
                'final class RejectingPropertyHook { public string $value = "" { set(string $value) { if ($value === "rejected") { throw new InvalidArgumentException("The hook rejected the value."); } $this->value = $value; } } }',
            ),
            'Could not create RejectingPropertyHook from the JSON object: The hook rejected the value.',
            DecodeErrorKind::ConstructorFailure->value,
        ];
        yield 'readonly property set by the constructor cannot be assigned again' => [
            '{"value":"other"}',
            OmittedInitializedReadonlyPublicProperty::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\OmittedInitializedReadonlyPublicProperty from the JSON object: Cannot modify readonly property Eventjet\Json\Test\Acceptance\Fixtures\OmittedInitializedReadonlyPublicProperty::$value',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string|JsonType<list<mixed>|object>, 2: string, 3: int}>
     * @throws \ReflectionException
     */
    public static function declarations(): iterable
    {
        yield 'private constructor cannot be called' => [
            '{}',
            PrivateConstructorTarget::create()::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget from the JSON object: Target class has a private constructor, which cannot be called to create the object.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'variadic constructor cannot bind JSON members' => [
            '{}',
            new VariadicConstructorTarget('value')::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\VariadicConstructorTarget from the JSON object: Constructor parameter values is variadic. JSON members bind to individual named arguments, not variadic argument lists.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'constructor parameter cannot bind a static property' => [
            '{}',
            new StaticConstructorParameterProperty('value')::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\StaticConstructorParameterProperty from the JSON object: Constructor parameter value has no same-named declared public instance property. The target class does not expose a stable JSON member from which the argument can be recovered.',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield from self::unknownClasses(Person::class);
    }

    /**
     * @param class-string $class
     * @return iterable<string, array{string, class-string|JsonType<list<mixed>>, string, int}>
     */
    private static function unknownClasses(string $class): iterable
    {
        /** @var class-string $missingClass */
        $missingClass = $class . 'Missing';

        yield 'unknown root class' => [
            '{}',
            $missingClass,
            'Could not create ' . $missingClass . ' from the JSON object: Class "' . $missingClass . '" does not exist',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
        yield 'unknown item class' => [
            '[]',
            JsonType::array($missingClass),
            'Could not create ' . $missingClass . ' from the JSON object: Class "' . $missingClass . '" does not exist',
            DecodeErrorKind::UnsupportedDeclaration->value,
        ];
    }
}
