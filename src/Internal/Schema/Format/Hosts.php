<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use Eventjet\Json\Internal\Schema\Draft;

use function explode;
use function preg_match;
use function strlen;

/**
 * Checks for the formats of host names.
 *
 * @internal
 */
final class Hosts
{
    public static function hostname(string $value, Draft $draft): bool
    {
        if ($value === '' || strlen($value) > 253) {
            return false;
        }
        foreach (explode('.', $value) as $label) {
            $valid = preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/D', $label) === 1;
            if (!$valid) {
                return false;
            }
            $reserved = $draft->atLeast(Draft::Draft7) && preg_match('/^..--/', $label) === 1;
            if (!$reserved) {
                continue;
            }
            $valid = preg_match('/^xn--/i', $label) === 1 && IdnHosts::hostname($label);
            if (!$valid) {
                return false;
            }
        }
        return true;
    }
}
