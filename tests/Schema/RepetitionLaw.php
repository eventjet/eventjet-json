<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationError;
use Eventjet\Json\Schema\Validator;
use PHPUnit\Framework\Assert;
use stdClass;

use function in_array;
use function is_string;
use function strlen;

/**
 * JSON Pointers and URI templates stay valid when copies of a valid one are joined. Joined to more than 100 KB, they
 * check that the format doesn't hit PCRE's limits. One match that repeats a group reached them at 6 to 16 KB.
 *
 * @internal
 */
final class RepetitionLaw
{
    private const int LENGTH = 100_000;

    /** @param ValidationError|null $error What validating the value returned. */
    public static function assertHolds(
        mixed $schema,
        Validator $validator,
        mixed $value,
        ValidationError|null $error,
    ): void {
        /** @var mixed $format */
        $format = $schema instanceof stdClass ? ((array) $schema)['format'] ?? null : null;
        $joinable = in_array($format, ['json-pointer', 'uri-template'], strict: true);
        if (!$joinable || $error !== null || !is_string($value) || $value === '') {
            return;
        }
        $joined = $value;
        while (strlen($joined) <= self::LENGTH) {
            $joined .= $joined;
        }
        Assert::assertNull($validator->validateValue($joined), $value);
    }
}
