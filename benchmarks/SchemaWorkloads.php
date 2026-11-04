<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\JsonSchema;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function array_keys;
use function file_get_contents;
use function is_bool;
use function json_decode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Schemas and values for compiling and validating: the 2020-12 meta-schema checking itself, which exercises
 * `$dynamicRef` and every applicator, and an order schema checking a batch of orders, as an API would.
 *
 * @internal
 */
final class SchemaWorkloads
{
    public const string PREFIX = 'schema: ';

    private const string ORDER_SCHEMA = <<<'JSON'
        {
            "$schema": "https://json-schema.org/draft/2020-12/schema",
            "$defs": {
                "money": {"type": "object", "required": ["amount", "currency"], "additionalProperties": false,
                    "properties": {"amount": {"type": "integer", "minimum": 0}, "currency": {"enum": ["EUR", "USD"]}}},
                "line": {"type": "object", "required": ["sku", "quantity", "price"], "unevaluatedProperties": false,
                    "properties": {"sku": {"type": "string", "pattern": "^[A-Z]{3}-\\d{4}$"},
                        "quantity": {"type": "integer", "minimum": 1}, "price": {"$ref": "#/$defs/money"}},
                    "anyOf": [{"properties": {"gift": {"const": true}}, "required": ["gift"]}, true]}
            },
            "type": "array",
            "items": {"type": "object", "required": ["id", "lines"], "properties": {
                "id": {"type": "string", "minLength": 1, "maxLength": 36},
                "email": {"type": "string", "format": "email"},
                "lines": {"type": "array", "minItems": 1, "items": {"$ref": "#/$defs/line"}},
                "tags": {"type": "array", "uniqueItems": true, "items": {"type": "string"}}
            }}
        }
        JSON;

    /**
     * @api Used by PHPBench parameter providers.
     * @return iterable<string, array{scenario: string}>
     */
    public static function scenarios(): iterable
    {
        foreach (self::names() as $name) {
            yield $name => ['scenario' => $name];
        }
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::factories());
    }

    /** @api Used by the PHP-FPM request script in CI. */
    public static function handles(string $name): bool
    {
        return str_starts_with($name, self::PREFIX);
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function named(string $name): SchemaWorkload
    {
        $factory = self::factories()[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException('Unknown schema workload: ' . $name);
        }
        return $factory();
    }

    /** @return array<string, callable(): SchemaWorkload> */
    private static function factories(): array
    {
        return [
            self::PREFIX . 'meta-schema 2020-12' => self::metaSchema(...),
            self::PREFIX . 'orders 100' =>
                /**
                 * @throws JsonException
                 * @throws RuntimeException
                 */
                static fn(): SchemaWorkload => self::orders(100),
        ];
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function metaSchema(): SchemaWorkload
    {
        $json = file_get_contents(__DIR__ . '/../resources/meta-schemas/2020-12/schema.json');
        if ($json === false) {
            throw new RuntimeException('Could not read the meta-schema.');
        }
        return new SchemaWorkload(self::decode($json), json_decode($json, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    private static function orders(int $count): SchemaWorkload
    {
        $orders = [];
        for ($index = 0; $index < $count; ++$index) {
            $orders[] = [
                'id' => 'order-' . $index,
                'email' => 'customer' . $index . '@example.com',
                'lines' => [
                    [
                        'sku' => 'ABC-' . (1000 + $index),
                        'quantity' => 1 + ($index % 3),
                        'price' => ['amount' => 1999, 'currency' => 'EUR'],
                    ],
                    [
                        'sku' => 'XYZ-0001',
                        'quantity' => 2,
                        'price' => ['amount' => 500, 'currency' => 'USD'],
                        'gift' => true,
                    ],
                ],
                'tags' => ['web', 'tag-' . $index],
            ];
        }
        return new SchemaWorkload(self::decode(self::ORDER_SCHEMA), $orders);
    }

    /** @throws RuntimeException */
    private static function decode(string $json): Draft202012
    {
        $schema = JsonSchema::decode($json, Draft202012::class);
        if ($schema instanceof DecodeError || is_bool($schema) || !$schema instanceof Draft202012) {
            throw new RuntimeException('Could not decode the benchmark schema.');
        }
        return $schema;
    }
}
