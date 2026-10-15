<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\PhpDocLiteral;
use Override;

use function array_column;
use function array_unique;
use function array_values;
use function get_debug_type;
use function is_float;

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

    /** Returns the PHP value of a single literal. */
    public function value(): string|int|float|bool|BackedEnum
    {
        return $this->values[0][2];
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
        foreach ($literals as $literal) {
            foreach ($literal->values as $value) {
                $values[$value[0]][] = $value;
            }
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
            ? InvalidValue::typeMismatch($path, $this->type, $value)
            : $this->fallback->decode($value, $path);
    }

    #[Override]
    public function valueTypes(): array
    {
        /** @var list<string> $valueTypes */
        $valueTypes = array_column($this->values, 0);
        return array_values(array_unique($valueTypes));
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
