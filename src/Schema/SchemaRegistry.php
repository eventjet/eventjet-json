<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use function array_key_exists;
use function count;
use function explode;

final class SchemaRegistry
{
    /** @var array<string, Schema> */
    private array $definitions = [];
    /** @var array<string, null> */
    private array $inProgress = [];
    /** @var array<string, null> */
    private array $selfReferenced = [];

    public function markInProgress(string $className): void
    {
        $this->inProgress[$className] = null;
    }

    public function isInProgress(string $className): bool
    {
        $result = array_key_exists($className, $this->inProgress);
        if ($result) {
            $this->selfReferenced[$className] = null;
        }
        return $result;
    }

    public function isSelfReferenced(string $className): bool
    {
        return array_key_exists($className, $this->selfReferenced);
    }

    public function register(string $className, Schema $schema): void
    {
        unset($this->inProgress[$className]);
        $this->definitions[$className] = $schema;
    }

    public function has(string $className): bool
    {
        return array_key_exists($className, $this->definitions)
            || array_key_exists($className, $this->inProgress);
    }

    public function refPath(string $className): string
    {
        $parts = explode('\\', $className);
        return '#/$defs/' . $parts[count($parts) - 1];
    }

    /**
     * @return array<string, Schema>
     */
    public function definitions(): array
    {
        $defs = [];
        foreach ($this->definitions as $className => $schema) {
            $parts = explode('\\', $className);
            $defs[$parts[count($parts) - 1]] = $schema;
        }
        return $defs;
    }
}
