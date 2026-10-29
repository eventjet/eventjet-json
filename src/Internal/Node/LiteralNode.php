<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\PhpDocLiteral;
use Override;

use function array_map;
use function array_merge;
use function get_debug_type;
use function is_float;
use function var_export;

/**
 * Accepts the JSON values of PHPDoc literals, such as `'draft'`, `42`, or `Status::Ready`, and returns their PHP values.
 * In a union, it holds the literals of one JSON value type and passes other values of that type to the member that
 * accepts them.
 *
 * @internal
 */
final readonly class LiteralNode implements Node
{
    /**
     * @param non-empty-list<array{string, string|int|float|bool, string|int|float|bool|BackedEnum}> $values The JSON
     *     value type, JSON value, and PHP value of each literal.
     */
    private function __construct(
        private string $type,
        private array $values,
        private Node|null $fallback,
    ) {}

    /** Returns the node of a single literal. */
    public static function of(PhpDocLiteral $literal): self
    {
        $value = $literal->value;
        $json = $value instanceof BackedEnum ? $value->value : $value;
        $type = match ($json) {
            true => 'true',
            false => 'false',
            default => get_debug_type($json),
        };
        $values = [[$type, $json, $value]];
        // A whole float may arrive as a JSON integer.
        $whole = is_float($json) && $json === (float) (int) $json;
        if ($whole) {
            $values[] = ['int', (int) $json, $value];
        }
        return new self($literal->name, $values, null);
    }

    /**
     * Combines the literals of a union by the JSON value types they accept. A member that already accepts a value type
     * receives the values that match no literal.
     *
     * @param list<self> $literals
     * @param array<string, Node> $indexed Union members, keyed by the JSON value types they accept.
     * @return array<string, Node>
     */
    public static function index(string $type, array $literals, array $indexed): array
    {
        $values = [];
        $all = array_merge(...array_map(
            /** @return non-empty-list<array{string, string|int|float|bool, string|int|float|bool|BackedEnum}> */
            static fn(self $literal): array => $literal->values,
            $literals,
        ));
        foreach ($all as $value) {
            $values[$value[0]][] = $value;
        }
        foreach ($values as $valueType => $accepted) {
            $indexed[$valueType] = new self($type, $accepted, $indexed[$valueType] ?? null);
        }
        return $indexed;
    }

    #[Override]
    public function decode(mixed $value, string $path): mixed
    {
        foreach ($this->values as [, $json, $literal]) {
            if ($json === $value) {
                return $literal;
            }
        }
        return $this->fallback === null
            ? throw InvalidValue::typeMismatch($path, $this->type, $value)
            : $this->fallback->decode($value, $path);
    }

    #[Override]
    public function claims(): array
    {
        $listed = [];
        foreach ($this->values as [$type, $json, $value]) {
            $listed[$type][var_export($json, return: true)] = [$json, $value];
        }
        return array_map(
            /**
             * @param array<string, array{string|int|float|bool, string|int|float|bool|BackedEnum}> $values
             * @return array{every: false, values: array<string, array{string|int|float|bool, string|int|float|bool|BackedEnum}>}
             */
            static fn(array $values): array => ['every' => false, 'values' => $values],
            $listed,
        );
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
