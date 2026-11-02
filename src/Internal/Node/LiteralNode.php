<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\PhpDocLiteral;
use Override;

use function get_debug_type;
use function is_float;
use function var_export;

/**
 * Accepts the JSON values of PHPDoc literals, such as `'draft'`, `42`, or `Status::Ready`, and returns their PHP values.
 * In a union, it holds the literals of one JSON value type and passes other values of that type to the member that
 * accepts them.
 *
 * @psalm-import-type ClaimValues from Node
 * @phpstan-import-type ClaimValues from Node
 * @internal
 */
final readonly class LiteralNode implements Node
{
    /**
     * @param list<array{string, string|int|float|bool, string|int|float|bool|BackedEnum}> $values The JSON value type,
     *     JSON value, and PHP value of each literal.
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
     * Returns the node of a union for the literals of one JSON value type. The fallback receives the values that match
     * no literal.
     *
     * @param ClaimValues $values
     */
    public static function table(string $type, string $valueType, array $values, Node|null $fallback): self
    {
        $listed = [];
        foreach ($values as [$json, $value]) {
            $listed[] = [$valueType, $json, $value];
        }
        return new self($type, $listed, $fallback);
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
        $claims = [];
        foreach ($this->values as [$type, $json, $value]) {
            $claims[$type] ??= ['others' => null, 'enums' => [], 'values' => []];
            $claims[$type]['values'][var_export($json, return: true)] = [$json, $value];
        }
        return $claims;
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
