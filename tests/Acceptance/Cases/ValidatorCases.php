<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\SchemaError;
use Eventjet\Json\Schema\Type;
use Eventjet\Json\Schema\Validator;
use InvalidArgumentException;
use JsonException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionException;
use RuntimeException;
use stdClass;

use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const PHP_INT_MIN;

/**
 * Generated values and schemas for the laws of the JSON Schema validator, and the error values that it returns.
 *
 * @internal
 */
final class ValidatorCases
{
    private const int VALUES = 40;

    private const int SCHEMAS = 8;

    private const int VALUES_PER_SCHEMA = 6;

    private const array DRAFTS = [Draft4::class, Draft6::class, Draft7::class, Draft201909::class, Draft202012::class];

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{mixed}>
     * @throws InvalidArgumentException
     */
    public static function values(): iterable
    {
        for ($seed = 1; $seed <= self::VALUES; $seed++) {
            yield sprintf('generated value, seed %d', $seed) => [self::value($seed)];
        }
    }

    /**
     * Pairs of values that are equal under JSON Schema equality, and pairs that mostly aren't.
     *
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{mixed, mixed}>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public static function valuePairs(): iterable
    {
        for ($seed = 1; $seed <= self::VALUES; $seed++) {
            $value = self::value($seed);
            yield sprintf('a value and itself, seed %d', $seed) => [$value, $value];
            yield sprintf('a value and an equal variant, seed %d', $seed) => [
                $value,
                EqualVariants::of(self::decoded($value)),
            ];
            yield sprintf('two generated values, seed %d', $seed) => [$value, self::value($seed + 1000)];
        }
        yield 'an integer and the equal float' => [1, 1.0];
        yield 'the smallest integer and the equal float' => [PHP_INT_MIN, (float) PHP_INT_MIN];
        yield 'the smallest integer and the opposite float' => [PHP_INT_MIN, -(float) PHP_INT_MIN];
        yield 'zero and negative zero' => [0, -0.0];
        yield 'false and zero' => [false, 0];
        yield 'null and an empty string' => [null, ''];
        yield 'an empty list and an empty object' => [[], new stdClass()];
        yield 'lists in a different order' => [[1, 2], [2, 1]];
    }

    /**
     * Generated schemas of every draft that compile, each with generated values.
     *
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Draft4|Draft6|Draft7|Draft201909|Draft202012, mixed}>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function schemasWithValues(): iterable
    {
        foreach (self::DRAFTS as $draft) {
            for ($seed = 1; $seed <= self::SCHEMAS; $seed++) {
                $schema = SchemaInputs::schema(new Randomizer(new Mt19937($seed)), $draft);
                $validator = Validator::compile($schema);
                if ($validator instanceof SchemaError) {
                    continue;
                }
                for ($value = 1; $value <= self::VALUES_PER_SCHEMA; $value++) {
                    yield sprintf('generated %s, seed %d, value %d', $draft, $seed, $value) => [
                        $schema,
                        self::value(($seed * 100) + $value),
                    ];
                }
            }
        }
        $generics = [
            new Draft4(type: [Type::Object, Type::Array, Type::Integer], minItems: 1, maxProperties: 2),
            new Draft6(type: [Type::Object, Type::Array, Type::Integer], minItems: 1, maxProperties: 2),
            new Draft7(type: [Type::Object, Type::Array, Type::Integer], minItems: 1, maxProperties: 2),
            new Draft201909(type: [Type::Object, Type::Array, Type::Integer], minItems: 1, maxProperties: 2),
            new Draft202012(type: [Type::Object, Type::Array, Type::Integer], minItems: 1, maxProperties: 2),
        ];
        foreach ($generics as $generic) {
            for ($value = 1; $value <= self::VALUES; $value++) {
                yield sprintf('%s with several types, value %d', $generic::class, $value) => [
                    $generic,
                    self::value($value),
                ];
            }
        }
    }

    /**
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws InvalidArgumentException
     */
    private static function value(int $seed): array|bool|float|int|object|string|null
    {
        return JsonValueInputs::value(new Randomizer(new Mt19937($seed)), 0);
    }

    /** @throws JsonException */
    private static function decoded(mixed $value): mixed
    {
        return json_decode(
            json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
