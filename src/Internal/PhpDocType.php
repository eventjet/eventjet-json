<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use function array_pop;
use function count;
use function preg_match;

/** @internal */
final readonly class PhpDocType
{
    /**
     * @param list<self> $arguments
     * @param list<PhpDocTupleEntry> $entries
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
        public array $entries = [],
    ) {}

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

    /** Whether this is an integer range such as `int<0, max>`. */
    public function isIntegerRange(): bool
    {
        if ($this->name !== 'int' || count($this->arguments) !== 2) {
            return false;
        }
        foreach ($this->arguments as $index => $bound) {
            $endpoint = $index === 0 ? 'min' : 'max';
            $valid = preg_match('/\A(?:' . $endpoint . '|-?[0-9]+)\z/', $bound->name) === 1;
            if ($bound->arguments !== [] || !$valid) {
                return false;
            }
        }
        return true;
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

    public function isPlainName(string $name): bool
    {
        return $this->name === $name && $this->arguments === [];
    }
}
