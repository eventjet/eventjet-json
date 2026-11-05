<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function array_key_exists;
use function max;

/**
 * The capturing groups of a regex and the back references to them, which may come before the groups.
 *
 * @internal
 */
final class Groups
{
    private int $count = 0;

    /** @var array<string, true> */
    private array $names = [];

    private int $maxReference = 0;

    /** @var list<string> */
    private array $namedReferences = [];

    public function open(): void
    {
        $this->count++;
    }

    /** Returns false if a group with the name already exists. */
    public function openNamed(string $name): bool
    {
        if (array_key_exists($name, $this->names)) {
            return false;
        }
        $this->names[$name] = true;
        $this->count++;
        return true;
    }

    public function reference(int $number): void
    {
        $this->maxReference = max($this->maxReference, $number);
    }

    public function referenceNamed(string $name): void
    {
        $this->namedReferences[] = $name;
    }

    /** @mutation-free */
    public function referencesResolve(): bool
    {
        if ($this->maxReference > $this->count) {
            return false;
        }
        foreach ($this->namedReferences as $name) {
            if (!array_key_exists($name, $this->names)) {
                return false;
            }
        }
        return true;
    }
}
