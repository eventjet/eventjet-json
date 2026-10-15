<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;

use function array_pop;
use function count;

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

    /** @return array{self, self}|null */
    private function argumentPair(): array|null
    {
        $arguments = $this->arguments;
        if (count($arguments) !== 2) {
            return null;
        }
        $first = $arguments[0];
        $second = array_pop($arguments);
        return [$first, $second];
    }

    /** Returns `T` if this is `T|null` or `null|T`. */
    public function withoutNull(): self|null
    {
        $pair = $this->name === '|' ? $this->argumentPair() : null;
        if ($pair === null) {
            return null;
        }
        [$first, $second] = $pair;
        $firstIsNull = $first->isPlainName('null');
        $secondIsNull = $second->isPlainName('null');
        return match (true) {
            $firstIsNull => $second,
            $secondIsNull => $first,
            default => null,
        };
    }

    /** Returns `T` if this has the arguments `<string, T>`. */
    public function stringKeyedValue(): self|null
    {
        $pair = $this->argumentPair();
        $hasStringKey = $pair !== null && $pair[0]->isPlainName('string');
        return $hasStringKey ? $pair[1] : null;
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
