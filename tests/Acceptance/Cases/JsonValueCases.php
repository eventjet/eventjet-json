<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonValueFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\OptionalJsonValue;
use InvalidArgumentException;
use JsonException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use stdClass;

use function array_map;
use function is_array;
use function json_encode;
use function sha1;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Cases for JsonValue. Generated inputs check two laws: a JsonValue stores what json_decode() reads from json_encode()
 * of its input, with the same PHP types, and decoding gives back exactly that.
 *
 * @internal
 */
final class JsonValueCases
{
    private const int GENERATED = 40;

    /**
     * @return iterable<string, array{object}>
     * @throws InvalidArgumentException
     */
    public static function objects(): iterable
    {
        $value = new JsonValue(['a' => [1, 2.5, null], 'b' => new stdClass(), '7' => []]);
        yield 'JsonValue in every position' => [new JsonValueFields(
            $value,
            [new JsonValue(null), $value, new JsonValue([])],
            new ArrayObject(['a' => new JsonValue(true), '' => new JsonValue(new ArrayObject())]),
            ['x' => new JsonValue('text')],
            [new JsonValue(-7), new JsonValue(1e100)],
        )];
        yield 'omitted optional JsonValue' => [new OptionalJsonValue()];
        $optional = new OptionalJsonValue(new JsonValue(null));
        $optional->extra = new JsonValue([null]);
        yield 'present optional JsonValue that holds null' => [$optional];
        yield 'omitted optional JsonValue before a present one' => [new OptionalJsonValue(new JsonValue(1))];
    }

    /**
     * @return iterable<string, array{string, class-string, callable(object): bool}>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public static function documents(): iterable
    {
        for ($seed = 1; $seed <= self::GENERATED; $seed++) {
            yield sprintf('generated JsonValue fields, seed %d', $seed) => self::generated($seed);
        }
        $object = new JsonValue(['a' => 1]);
        $list = new JsonValue([1]);
        yield 'value() returns copies' => [
            '{"value":{"a":1},"items":[],"members":{},"nonEmpty":{"a":[1]},"tuple":[1]}',
            JsonValueFields::class,
            /** @throws InvalidArgumentException */
            static function (object $decoded) use ($object, $list): bool {
                $members = $object->value();
                $items = $list->value();
                if (!$members instanceof ArrayObject || !is_array($items) || !$decoded instanceof JsonValueFields) {
                    return false;
                }
                $members['b'] = new JsonValue(2);
                $items[] = new JsonValue(2);
                $decodedMembers = $decoded->value->value();
                if ($decodedMembers instanceof ArrayObject) {
                    $decodedMembers['b'] = new JsonValue(2);
                }
                return (
                    json_encode($object) === '{"a":1}'
                    && json_encode($list) === '[1]'
                    && json_encode($decoded->value) === '{"a":1}'
                );
            },
        ];
    }

    /**
     * @return iterable<string, array{string, object}>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function decoded(): iterable
    {
        yield 'absent JsonValue|null member stays null' => ['{}', new OptionalJsonValue()];
        yield 'JSON null in a JsonValue|null member is a JsonValue' => [
            '{"value":null}',
            new OptionalJsonValue(new JsonValue(null)),
        ];
        $property = new OptionalJsonValue();
        $property->extra = new JsonValue(null);
        yield 'JSON null in a JsonValue|null property is a JsonValue' => ['{"extra":null}', $property];
        $class = CollectionNameSource::load(
            'JsonValueInheritedProperty',
            'abstract class JsonValueInheritedPropertyBase { public ?\\'
            . JsonValue::class
            . ' $value = null; } final class JsonValueInheritedProperty extends JsonValueInheritedPropertyBase'
            . ' implements \\JsonSerializable { public function jsonSerialize(): mixed { return (object) []; } }',
        );
        $inherited = new ReflectionClass($class);
        $object = $inherited->newInstance();
        $inherited->getProperty('value')->setValue($object, new JsonValue(null));
        yield 'inherited JsonValue|null property of a JsonSerializable class' => ['{"value":null}', $object];
        $class = CollectionNameSource::load(
            'JsonValueAfterOtherProperty',
            'final class JsonValueAfterOtherProperty implements \\JsonSerializable { public string $label = ""; public ?\\'
            . JsonValue::class
            . ' $value = null; public function jsonSerialize(): mixed { return (object) []; } }',
        );
        $later = new ReflectionClass($class);
        $object = $later->newInstance();
        $later->getProperty('value')->setValue($object, new JsonValue(null));
        yield 'JsonValue|null property after another property' => ['{"value":null}', $object];
        $class = CollectionNameSource::load(
            'JsonValueOrNonBackedEnum',
            'final class JsonValueOrNonBackedEnum { public function __construct(public \\'
            . JsonValue::class
            . '|\\'
            . NonBackedStatus::class
            . ' $value) {} }',
        );
        $values = [
            'null' => null,
            'true' => true,
            'false' => false,
            '1' => 1,
            '1.5' => 1.5,
            '"text"' => 'text',
            '[1,{"a":null}]' => [1, (object) ['a' => null]],
            '{"a":[]}' => (object) ['a' => []],
        ];
        foreach ($values as $json => $value) {
            yield 'JsonValue in a union with an enum that JSON cannot represent, holding ' . $json => [
                '{"value":' . $json . '}',
                new ReflectionClass($class)->newInstance(new JsonValue($value)),
            ];
        }
        foreach (JsonValueLimits::nested(512) as $label => $input) {
            $class = JsonValueErrorCases::constructing('JsonValueMaxDepth_' . sha1($input), $input);
            yield $label . ' nested 512 levels deep' => ['{}', new ReflectionClass($class)->newInstance()];
        }
    }

    /**
     * @return array{string, class-string, callable(object): bool}
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private static function generated(int $seed): array
    {
        $random = new Randomizer(new Mt19937($seed));
        $value = JsonValueInputs::value($random, 0);
        $items = JsonValueInputs::listOf($random, 0, 0);
        $members = JsonValueInputs::mapOf($random);
        $nonEmpty =
            [
                JsonValueInputs::pick($random, JsonValueInputs::STRING_NAMES) => JsonValueInputs::value($random, 0),
            ] + JsonValueInputs::mapOf($random);
        $first = JsonValueInputs::value($random, 0);
        $second = JsonValueInputs::value($random, 0);
        $long = ($seed % 2) === 0;
        $original = new JsonValueFields(
            new JsonValue($value),
            array_map(self::wrap(...), $items),
            new ArrayObject(array_map(self::wrap(...), $members)),
            array_map(self::wrap(...), $nonEmpty),
            $long ? [new JsonValue($first), new JsonValue($second)] : [new JsonValue($first)],
        );
        $expected = [
            'value' => JsonValueTrees::reference($value),
            'items' => array_map(JsonValueTrees::reference(...), $items),
            'members' => array_map(JsonValueTrees::reference(...), $members),
            'nonEmpty' => array_map(JsonValueTrees::reference(...), $nonEmpty),
            'tuple' => array_map(JsonValueTrees::reference(...), $long ? [$first, $second] : [$first]),
        ];
        return [
            json_encode($original, JSON_THROW_ON_ERROR),
            JsonValueFields::class,
            static fn(object $decoded): bool => (
                $decoded instanceof JsonValueFields
                && JsonValueTrees::fields($original) === $expected
                && JsonValueTrees::fields($decoded) === $expected
            ),
        ];
    }

    /** @throws InvalidArgumentException */
    private static function wrap(mixed $value): JsonValue
    {
        return new JsonValue($value);
    }
}
