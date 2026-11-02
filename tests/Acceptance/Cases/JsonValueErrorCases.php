<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Acceptance\Fixtures\FailingSerializer;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonValueFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\SerializedJson;
use InvalidArgumentException;
use RuntimeException;

use function sha1;
use function sprintf;

/** @internal */
final class JsonValueErrorCases
{
    /**
     * @return iterable<string, array{0: string, 1: class-string|JsonType<list<mixed>>, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        yield from self::declarations();
        $fields = '"items":[],"members":{},"nonEmpty":{"a":1},"tuple":[1]';
        foreach ([
            'field' => ['{"value":1e400,' . $fields . '}', 'value', 'INF'],
            'list item' => [
                '{"value":1,"items":[1,-1e400],"members":{},"nonEmpty":{"a":1},"tuple":[1]}',
                'items[1]',
                '-INF',
            ],
            'map value' => [
                '{"value":1,"items":[],"members":{"a":[1e400]},"nonEmpty":{"a":1},"tuple":[1]}',
                'members[a]',
                'INF',
            ],
            'tuple position' => [
                '{"value":1,"items":[],"members":{},"nonEmpty":{"a":1},"tuple":[1,{"a":1e400}]}',
                'tuple[1]',
                'INF',
            ],
        ] as $position => [$json, $path, $float]) {
            yield 'number too large for a float in a JsonValue ' . $position => [
                $json,
                JsonValueFields::class,
                sprintf(
                    'Could not create %s from the JSON object: Field %s is not a valid JSON value. JsonValue cannot hold the float %s, which has no JSON representation.',
                    JsonValueFields::class,
                    $path,
                    $float,
                ),
                DecodeErrorKind::InvalidValue,
            ];
        }
        yield 'JSON string for a list of JsonValue' => [
            '{"value":1,"items":"a","members":{},"nonEmpty":{"a":1},"tuple":[1]}',
            JsonValueFields::class,
            sprintf(
                'Could not create %s from the JSON object: Field items must be of type list<%s>, string given.',
                JsonValueFields::class,
                JsonValue::class,
            ),
            DecodeErrorKind::InvalidValue,
        ];
        yield from self::rejectedInputs();
    }

    /**
     * Declarations that cannot decode, whatever the input.
     *
     * @return iterable<string, array{string, class-string|JsonType<list<mixed>>, string, DecodeErrorKind}>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function declarations(): iterable
    {
        $jsonValue = '\\' . JsonValue::class;
        foreach (JsonValueLimits::unions() as $label => [$body, $path]) {
            $class = self::declaring('JsonValueUnion_' . sha1($body), $body, '');
            yield 'JsonValue in a union ' . $label => [
                '{}',
                $class,
                sprintf(
                    'Could not create %s from the JSON object: Field %s uses %s in a union. It takes every JSON value, null included, so JSON cannot identify another member.',
                    $class,
                    $path,
                    JsonValue::class,
                ),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        foreach ([
            'constructor parameter' => 'public function __construct(public ?' . $jsonValue . ' $value = null) {}',
            'public property' => 'public ?' . $jsonValue . ' $value = null;',
        ] as $label => $body) {
            $class = self::declaring('JsonValueWithoutSerializer_' . sha1($body), $body, '');
            yield 'JsonValue|null ' . $label . ' without JsonSerializable' => [
                '{}',
                $class,
                sprintf(
                    'Could not create %1$s from the JSON object: Field value uses %2$s|null, which requires the class to implement JsonSerializable. json_encode() writes a null property as JSON null, which decodes as a %2$s that holds null, so the serializer must leave out the member instead.',
                    $class,
                    JsonValue::class,
                ),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        $class = self::declaring(
            'JsonValueOtherProperties',
            'private ?'
            . $jsonValue
            . ' $private = null; public static ?'
            . $jsonValue
            . ' $static = null; public '
            . $jsonValue
            . ' $required; public ?string $string = null; public int|string|null $union = null; public $untyped;'
            . ' public function jsonSerialize(): mixed { return null; }',
            ' implements \\JsonSerializable',
        );
        yield 'JsonSerializable without #[Field] or a JsonValue|null property' => [
            '{}',
            $class,
            sprintf(
                'Could not create %s from the JSON object: Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
                $class,
            ),
            DecodeErrorKind::UnsupportedDeclaration,
        ];
        foreach ([
            'root' => ['{}', JsonValue::class],
            'root collection item' => ['[]', JsonType::array(JsonValue::class)],
        ] as $label => [$json, $type]) {
            yield 'JsonValue as the ' . $label => [
                $json,
                $type,
                sprintf(
                    'Could not create %1$s from the JSON object: Target type is %1$s, which is supported only in fields, list items, map values and tuple positions, not as the root type or as a JsonType item.',
                    JsonValue::class,
                ),
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
    }

    /**
     * Input that the JsonValue constructor rejects, passed by the constructor of the target class.
     *
     * @return iterable<string, array{0: string, 1: class-string, 2: string, 3: DecodeErrorKind, 4?: \Throwable}>
     * @throws RuntimeException
     */
    private static function rejectedInputs(): iterable
    {
        $accepts = 'It accepts null, scalars, arrays, stdClass, ArrayObject, JsonSerializable objects and backed enums.';
        $depth = 'JsonValue cannot hold values nested more than 512 levels deep, which json_encode() cannot write. Each jsonSerialize() call counts as a level.';
        foreach ([
            'closure' => ['static fn(): int => 1', 'JsonValue cannot hold Closure. ' . $accepts],
            'object of another class' => [
                '[new \\DateTimeImmutable()]',
                'JsonValue cannot hold DateTimeImmutable. ' . $accepts,
            ],
            'resource' => ['fopen("php://memory", "r")', 'JsonValue cannot hold resource (stream). ' . $accepts],
            'non-backed enum' => [
                '\\' . NonBackedStatus::class . '::Ready',
                'JsonValue cannot hold ' . NonBackedStatus::class . '. ' . $accepts,
            ],
            'INF' => ['INF', 'JsonValue cannot hold the float INF, which has no JSON representation.'],
            'NAN in an object' => [
                '(object) ["a" => NAN]',
                'JsonValue cannot hold the float NAN, which has no JSON representation.',
            ],
            'invalid UTF-8' => [
                '["\\xff"]',
                'Strings in a JsonValue must be valid UTF-8, which json_encode() requires.',
            ],
            'invalid UTF-8 member name' => [
                'new \\ArrayObject(["\\xff" => 1])',
                'Member names in a JsonValue must be valid UTF-8, which json_encode() requires.',
            ],
            'member name with a leading null byte' => [
                '["\\0a" => 1]',
                'Member names in a JsonValue must not start with a null byte, because json_decode() cannot read them.',
            ],
            'object that contains itself' => [
                '(static function (): \\stdClass { $object = new \\stdClass(); $object->self = $object; return $object; })()',
                $depth,
            ],
            'serializer that returns its own object' => [
                'new class implements \\JsonSerializable { public function jsonSerialize(): mixed { return $this; } }',
                $depth,
            ],
            'serializer that returns a closure' => [
                'new \\' . SerializedJson::class . '(static fn(): int => 1)',
                'JsonValue cannot hold Closure. ' . $accepts,
            ],
            'serializer that throws' => [
                'new \\' . FailingSerializer::class . '()',
                sprintf('JsonValue cannot hold %s, because its jsonSerialize() threw.', FailingSerializer::class),
            ],
        ] as $label => [$input, $message]) {
            $class = self::constructing('JsonValueRejected_' . sha1($input), $input);
            yield 'JsonValue rejects ' . $label => [
                '{}',
                $class,
                sprintf('Could not create %s from the JSON object: %s', $class, $message),
                DecodeErrorKind::ConstructorFailure,
            ];
        }
        foreach (JsonValueLimits::nested(513) as $label => $input) {
            $class = self::constructing('JsonValueRejected_' . sha1($input), $input);
            yield 'JsonValue rejects ' . $label . ' nested 513 levels deep' => [
                '{}',
                $class,
                sprintf('Could not create %s from the JSON object: %s', $class, $depth),
                DecodeErrorKind::ConstructorFailure,
            ];
        }
        $class = CollectionNameSource::load(
            'JsonValueSerializerFailure',
            'final class JsonValueSerializerFailure { public function __construct() { try { new \\'
            . JsonValue::class
            . '(new \\'
            . FailingSerializer::class
            . '()); } catch (\\InvalidArgumentException $error) { throw $error->getPrevious() ?? $error; } } }',
        );
        yield 'JsonValue keeps the exception of a serializer as the previous one' => [
            '{}',
            $class,
            sprintf('Could not create %s from the JSON object: Serializer failed.', $class),
            DecodeErrorKind::ConstructorFailure,
            FailingSerializer::error(),
        ];
    }

    /**
     * @return class-string
     * @throws RuntimeException
     */
    public static function constructing(string $class, string $input): string
    {
        return CollectionNameSource::load(
            $class,
            'final class '
            . $class
            . ' { public function __construct() { new \\'
            . JsonValue::class
            . '('
            . $input
            . '); } }',
        );
    }

    /**
     * @return class-string
     * @throws RuntimeException
     */
    private static function declaring(string $class, string $body, string $interface): string
    {
        return CollectionNameSource::load($class, 'final class ' . $class . $interface . ' {' . $body . '}');
    }
}
