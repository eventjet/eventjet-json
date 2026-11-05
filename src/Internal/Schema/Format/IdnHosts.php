<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Format;

use function explode;
use function idn_to_ascii;
use function idn_to_utf8;
use function preg_match;
use function strlen;

use const IDNA_CHECK_BIDI;
use const IDNA_CHECK_CONTEXTJ;
use const IDNA_DEFAULT;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_USE_STD3_RULES;
use const INTL_IDNA_VARIANT_UTS46;

/**
 * Checks for the format of internationalized host names, which needs the intl extension.
 *
 * @internal
 */
final class IdnHosts
{
    public static function hostname(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $endsWithDot = preg_match('/(?:^|[.\x{3002}\x{FF0E}\x{FF61}])$/uD', $value) === 1;
        if ($endsWithDot) {
            return false;
        }
        $info = [];
        $ascii = idn_to_ascii(
            $value,
            IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES,
            INTL_IDNA_VARIANT_UTS46,
            $info,
        );
        /** @var array{errors?: int} $info */
        if ($ascii === false || $ascii === '' || ($info['errors'] ?? 0) !== 0 || strlen($ascii) > 253) {
            return false;
        }
        $unicode = idn_to_utf8($ascii, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($unicode === false) {
            return false;
        }
        foreach (explode('.', $unicode) as $label) {
            $valid = IdnaContext::allows($label);
            if (!$valid) {
                return false;
            }
        }
        return true;
    }
}
