<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function array_key_exists;
use function count;

/**
 * The capturing groups of a regex and the back references to them, which may come before the groups.
 *
 * @internal
 */
final class Groups
{
    private int $count = 0;

    /** @var array<string, int> The number of each named group. */
    private array $names = [];

    /** @var array<string, string> The PCRE name of each group name, which PCRE might not accept as it is. */
    private array $pcreNames = [];

    /** @var array<int, true> The groups that are inside a quantified atom that can repeat. */
    private array $repeated = [];

    /** @var list<int> */
    private array $references = [];

    /** @var list<string> */
    private array $namedReferences = [];

    /** @mutation-free */
    public function count(): int
    {
        return $this->count;
    }

    public function open(): void
    {
        $this->count++;
    }

    /** Returns the PCRE name of the group, or null if a group with the name already exists. */
    public function openNamed(string $name): string|null
    {
        if (array_key_exists($name, $this->names)) {
            return null;
        }
        $this->count++;
        $this->names[$name] = $this->count;
        return $this->pcreName($name);
    }

    /** Marks the groups after the given number as inside a quantified atom that can repeat. */
    public function repeat(int $after): void
    {
        for ($number = $after + 1; $number <= $this->count; $number++) {
            $this->repeated[$number] = true;
        }
    }

    /** Returns a back reference to the group with the number as PCRE. */
    public function reference(int $number): string
    {
        $this->references[] = $number;
        return '(?(' . $number . ')\g{' . $number . '})';
    }

    /** Returns a back reference to the group with the name as PCRE. */
    public function referenceNamed(string $name): string
    {
        $this->namedReferences[] = $name;
        $pcreName = $this->pcreName($name);
        return '(?(<' . $pcreName . '>)\k<' . $pcreName . '>)';
    }

    /** Turns the references to names into references to numbers, or returns false if a reference has no group. */
    public function resolveReferences(): bool
    {
        foreach ($this->namedReferences as $name) {
            $number = $this->names[$name] ?? null;
            if ($number === null) {
                return false;
            }
            $this->references[] = $number;
        }
        foreach ($this->references as $number) {
            if ($number > $this->count) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns whether a back reference refers to a group that can repeat. ECMA-262 forgets the captures of a group at
     * the start of each repetition, but PCRE keeps them, so such a reference can match something else in PCRE. Call
     * it after {@see self::resolveReferences()}.
     *
     * @mutation-free
     */
    public function referencesRepeatedGroup(): bool
    {
        foreach ($this->references as $number) {
            if (array_key_exists($number, $this->repeated)) {
                return true;
            }
        }
        return false;
    }

    private function pcreName(string $name): string
    {
        return $this->pcreNames[$name] ??= 'n' . count($this->pcreNames);
    }
}
