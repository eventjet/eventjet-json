<?php

declare(strict_types=1);

namespace Eventjet\Json;

use function preg_match;
use function preg_quote;
use function trim;

final class DocblockParser
{
    /**
     * Extract the type annotation for a parameter from a docblock.
     *
     * @return string|null The type string (e.g., "list<string>") or null if not found
     */
    public function getParamType(string $docblock, string $paramName): string|null
    {
        // Match type including generic syntax with potential spaces (e.g., "array<string, int>")
        // Uses a more permissive pattern that captures until the $ of the variable name
        $pattern = '/@param\s+(?<type>.+?)\s+\$' . preg_quote($paramName, '/') . '(?:\s|$)/';

        if (preg_match($pattern, $docblock, $matches) !== 1) {
            return null;
        }

        return trim($matches['type']);
    }
}
