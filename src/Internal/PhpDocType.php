<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;

use function array_map;
use function array_pop;
use function count;
use function implode;
use function in_array;
use function sort;

/** @internal */
final readonly class PhpDocType
{
    /** Collection types, which name `array` in a native type. */
    public const array COLLECTIONS = [
        'array{}' => 'array',
        'list' => 'array',
        'non-empty-list' => 'array',
        'non-empty-array' => 'array',
        'non-empty-map' => 'array',
    ];

    /** Native types whose PHPDoc type may declare their items. */
    public const array CONTAINERS = ['array' => true, ArrayObject::class => true];

    /**
     * The first number, shape key, or integer range bound in this type that PHP and PHPStan can read differently, which
     * makes the declaration unsupported. See {@see PhpDocLiteral}.
     */
    public string|null $unsupportedNumber;

    /**
     * @param list<self> $arguments
     * @param list<PhpDocTupleEntry> $entries
     * @param string|null $unsupportedNumber A number token that reads as no literal, which this type stands for.
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
        public array $entries = [],
        public PhpDocLiteral|null $literal = null,
        string|null $unsupportedNumber = null,
    ) {
        $this->unsupportedNumber = $unsupportedNumber === null ? $this->nestedUnsupportedNumber() : $unsupportedNumber;
    }

    public static function literal(PhpDocLiteral $literal): self
    {
        return new self($literal->name, literal: $literal);
    }

    /** Returns the type of a resolved name, with `true` and `false` as literals. */
    public static function named(string $name): self
    {
        return $name === 'true' || $name === 'false' ? self::literal(PhpDocLiteral::boolean($name)) : new self($name);
    }

    private function nestedUnsupportedNumber(): string|null
    {
        if ($this->name === 'int' && count($this->arguments) === 2) {
            return PhpDocIntegerRange::unsupportedNumber($this);
        }
        foreach ([...$this->arguments, ...$this->entries] as $child) {
            if ($child->unsupportedNumber !== null) {
                return $child->unsupportedNumber;
            }
        }
        return null;
    }

    /**
     * Returns the arguments if this has exactly two.
     *
     * @return array{self, self}|null
     */
    public function argumentPair(): array|null
    {
        $arguments = $this->arguments;
        if (count($arguments) !== 2) {
            return null;
        }
        $first = $arguments[0];
        $second = array_pop($arguments);
        return [$first, $second];
    }

    /**
     * Returns which member names a `non-empty-array`, `non-empty-map`, or `ArrayObject` container accepts with this key
     * type, as written or with resolved names: `string` for names that PHP keeps as string keys, `array-key` for every
     * name, or null if the container does not accept this key type. `non-empty-array` maps keep string keys, because
     * `json_encode()` writes an array with integer keys as a list.
     *
     * @return 'string'|'array-key'|null
     *
     * @mutation-free
     */
    public function mapKeys(string $container): string|null
    {
        if ($this->isPlainName('string')) {
            return 'string';
        }
        return $container === ArrayObject::class && $this->isAnyKey() ? 'array-key' : null;
    }

    /**
     * Whether the type accepts every array key: `array-key` or `int|string`.
     *
     * @mutation-free
     */
    public function isAnyKey(): bool
    {
        $names = array_map(static fn(self $member): string|null => $member->plainName(), $this->members());
        sort($names);
        return in_array(implode('|', $names), ['array-key', 'int|string'], strict: true);
    }

    /**
     * Returns the name of a type without arguments or entries, such as `Foo` or `Foo::BAR`.
     *
     * @mutation-free
     */
    public function plainName(): string|null
    {
        return $this->arguments === [] && $this->entries === [] ? $this->name : null;
    }

    /** @mutation-free */
    public function isPlainName(string $name): bool
    {
        return $this->name === $name && $this->arguments === [];
    }

    /**
     * Returns the members of a union, or the type itself.
     *
     * @return list<PhpDocType>
     */
    public function members(): array
    {
        return $this->name === '|' ? $this->arguments : [$this];
    }
}
