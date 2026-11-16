<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use function explode;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The `compatibility` field of a case of the official JSON Schema Test Suite, which names the releases that the case
 * applies to.
 *
 * @internal
 */
final class OfficialSuiteCompatibility
{
    /**
     * Upstream's algorithm from the README of its validation suite. The constraints are checked in order, and the first
     * one whose release is not below the given one decides.
     *
     * @param int $release The release number that the suite uses, such as 4 or 2020.
     */
    public static function includes(string|null $compatibility, int $release): bool
    {
        if ($compatibility === null) {
            return true;
        }
        $includes = true;
        foreach (explode(',', $compatibility) as $constraint) {
            $operator = match (true) {
                str_starts_with($constraint, '<=') => '<=',
                str_starts_with($constraint, '=') => '=',
                default => '',
            };
            $version = (int) substr($constraint, strlen($operator));
            $includes = match ($operator) {
                '<=' => $release <= $version,
                '=' => $release === $version,
                default => $release >= $version,
            };
            if ($release <= $version) {
                break;
            }
        }
        return $includes;
    }
}
