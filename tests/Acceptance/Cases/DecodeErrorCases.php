<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayIterator;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractClassField;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractClassUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\InterfaceField;
use Eventjet\Json\Test\Acceptance\Fixtures\InterfaceUnionField;
use Eventjet\Json\Test\Acceptance\Fixtures\IntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableIntersectionTypeField;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;
use Eventjet\Json\Test\Acceptance\Fixtures\ThrowingConstructor;
use JsonException;
use RuntimeException;

use function str_repeat;

/** @internal */
final class DecodeErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function constructionFailures(): iterable
    {
        yield 'missing constructor arguments' => [
            '{}',
            Person::class,
            'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\Person from the JSON object: Too few arguments to function Eventjet\\Json\\Test\\Acceptance\\Fixtures\\Person::__construct(), 0 passed and at least 2 expected',
            3,
        ];

        $throwingConstructorTarget = new ThrowingConstructor('accepted');

        yield 'constructor throws an exception' => [
            '{"value":"rejected"}',
            $throwingConstructorTarget::class,
            'Could not create '
                . $throwingConstructorTarget::class
                . ' from the JSON object: The constructor rejected the decoded value.',
            3,
        ];

        yield 'interface root target' => [
            '{}',
            RootTargetInterface::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface from the JSON object: Target type is an interface. JSON does not identify a concrete implementation to instantiate.',
            3,
        ];

        yield 'abstract class root target' => [
            '{}',
            AbstractRootTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget from the JSON object: Target type is an abstract class. JSON does not identify a concrete subclass to instantiate.',
            3,
        ];

        $jsonSerializableTarget = new JsonSerializableTarget('Ada');

        yield 'JsonSerializable root target' => [
            '{"constructorName":"Ada"}',
            $jsonSerializableTarget::class,
            'Could not create Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget from the JSON object: Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            3,
        ];

        $interfaceFieldTarget = new InterfaceField(new class implements RootTargetInterface {});
        $interfaceFieldMessage =
            'Could not create '
            . $interfaceFieldTarget::class
            . ' from the JSON object: Field value uses interface '
            . RootTargetInterface::class
            . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';

        yield 'interface field, member present' => [
            '{"value":{}}',
            $interfaceFieldTarget::class,
            $interfaceFieldMessage,
            3,
        ];
        yield 'interface field, member absent' => ['{}', $interfaceFieldTarget::class, $interfaceFieldMessage, 3];

        $abstractClassFieldTarget = new AbstractClassField(new class extends AbstractRootTarget {});
        $abstractClassFieldMessage =
            'Could not create '
            . $abstractClassFieldTarget::class
            . ' from the JSON object: Field value uses abstract class '
            . AbstractRootTarget::class
            . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';

        yield 'abstract class field, member present' => [
            '{"value":{}}',
            $abstractClassFieldTarget::class,
            $abstractClassFieldMessage,
            3,
        ];
        yield 'abstract class field, member absent' => [
            '{}',
            $abstractClassFieldTarget::class,
            $abstractClassFieldMessage,
            3,
        ];

        $interfaceUnionTarget = new InterfaceUnionField('supported scalar');
        $interfaceUnionMessage =
            'Could not create '
            . $interfaceUnionTarget::class
            . ' from the JSON object: Field value uses interface '
            . RootTargetInterface::class
            . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';

        yield 'interface union member, scalar value' => [
            '{"value":"supported scalar"}',
            $interfaceUnionTarget::class,
            $interfaceUnionMessage,
            3,
        ];
        yield 'interface union member, field absent' => [
            '{}',
            $interfaceUnionTarget::class,
            $interfaceUnionMessage,
            3,
        ];

        $abstractClassUnionTarget = new AbstractClassUnionField('supported scalar');
        $abstractClassUnionMessage =
            'Could not create '
            . $abstractClassUnionTarget::class
            . ' from the JSON object: Field value uses abstract class '
            . AbstractRootTarget::class
            . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.';

        yield 'abstract class union member, scalar value' => [
            '{"value":"supported scalar"}',
            $abstractClassUnionTarget::class,
            $abstractClassUnionMessage,
            3,
        ];
        yield 'abstract class union member, field absent' => [
            '{}',
            $abstractClassUnionTarget::class,
            $abstractClassUnionMessage,
            3,
        ];

        $intersectionTarget = new IntersectionTypeField(new ArrayIterator([]));
        $intersectionMessage =
            'Could not create '
            . IntersectionTypeField::class
            . ' from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.';

        yield 'intersection type field, member present' => [
            '{"value":{}}',
            $intersectionTarget::class,
            $intersectionMessage,
            3,
        ];
        yield 'intersection type field, member absent' => [
            '{}',
            $intersectionTarget::class,
            $intersectionMessage,
            3,
        ];

        $nullableIntersectionTarget = new NullableIntersectionTypeField(null);
        $nullableIntersectionMessage =
            'Could not create '
            . NullableIntersectionTypeField::class
            . ' from the JSON object: Field value uses unsupported intersection type Countable&Iterator. JSON does not identify a concrete class to instantiate.';

        yield 'intersection type in nullable union, member null' => [
            '{"value":null}',
            $nullableIntersectionTarget::class,
            $nullableIntersectionMessage,
            3,
        ];
        yield 'intersection type in nullable union, member absent' => [
            '{}',
            $nullableIntersectionTarget::class,
            $nullableIntersectionMessage,
            3,
        ];

        yield from AmbiguousBackedEnumUnionErrorCases::errors();
        yield from BackedEnumErrorCases::errors();
        yield from InaccessibleConstructorErrorCases::errors();
        yield from MultipleClassUnionErrorCases::errors();
        yield from MultipleEnumUnionErrorCases::errors();
        yield from NestedObjectErrorCases::errors();
        yield from UnsupportedFieldTypeCases::errors();
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function malformedDocuments(): iterable
    {
        yield 'malformed JSON' => ['{', Person::class, 'Invalid JSON: Syntax error', 1];

        foreach ([
            'empty document' => '',
            'missing member value' => '{"firstName":}',
            'trailing comma' => '{"firstName":"Ada",}',
            'unquoted member name' => '{firstName:"Ada"}',
            'single-quoted string' => "{\"firstName\":'Ada'}",
            'invalid escape' => '{"firstName":"\\x"}',
        ] as $name => $json) {
            yield $name => [$json, Person::class, 'Invalid JSON: Syntax error', 1];
        }

        yield 'unterminated string' => [
            '{"firstName":"Ada}',
            Person::class,
            'Invalid JSON: Control character error, possibly incorrectly encoded',
            1,
        ];
        yield 'unescaped control character' => [
            "{\"firstName\":\"Ada\n\"}",
            Person::class,
            'Invalid JSON: Control character error, possibly incorrectly encoded',
            1,
        ];
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function documentsWithTrailingContent(): iterable
    {
        foreach (['{}', '[]', 'null', 'true', '42', '"text"'] as $document) {
            foreach ([' {}', ' []', ' null', ' true', ' 42', ' garbage'] as $suffix) {
                yield 'trailing content: ' . $document . $suffix => [
                    $document . $suffix,
                    Person::class,
                    'Invalid JSON: Syntax error',
                    1,
                ];
            }
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function invalidUtf8Documents(): iterable
    {
        foreach ([
            'lone continuation byte' => "\x80",
            'truncated sequence' => "\xc2",
            'overlong sequence' => "\xc0\xaf",
            'UTF-16 surrogate' => "\xed\xa0\x80",
            'beyond Unicode range' => "\xf4\x90\x80\x80",
        ] as $name => $bytes) {
            yield 'invalid UTF-8: ' . $name => [
                '{"firstName":"' . $bytes . '","lastName":"Lovelace"}',
                Person::class,
                'Invalid JSON: Malformed UTF-8 characters, possibly incorrectly encoded',
                1,
            ];
        }
    }

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function deeplyNestedDocuments(): iterable
    {
        foreach (['array' => ['[', ']'], 'object' => ['{"nested":', '}']] as $name => [$opening, $closing]) {
            foreach ([512, 513, 1024] as $depth) {
                yield $name . ' nesting depth: ' . $depth => [
                    str_repeat($opening, $depth) . '0' . str_repeat($closing, $depth),
                    Person::class,
                    'Invalid JSON: Maximum stack depth exceeded',
                    1,
                ];
            }
        }
    }
}
