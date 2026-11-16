<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\ValidationErrorKind;
use Eventjet\Json\Schema\Validator;
use JsonSerializable;
use Override;
use PHPUnit\Framework\Assert;
use RuntimeException;

use function json_encode;
use function json_last_error_msg;

use const INF;

/**
 * A value with a part that json_encode() rejects, or whose jsonSerialize() throws, is not JSON, whatever the schema.
 * The error says why, in the words of json_encode() or of the exception.
 *
 * @internal
 */
final class UnsupportedValueLaw
{
    public static function assertHolds(Validator $validator, mixed $value): void
    {
        $infinite = [$value, INF];
        Assert::assertFalse(json_encode($infinite));
        self::assertRejected($validator, $infinite, json_last_error_msg());
        $throwing = new class implements JsonSerializable {
            /** @throws RuntimeException */
            #[Override]
            public function jsonSerialize(): never
            {
                throw new RuntimeException('Not today.');
            }
        };
        self::assertRejected($validator, [$value, $throwing], 'jsonSerialize() threw: Not today.');
    }

    private static function assertRejected(Validator $validator, mixed $value, string $reason): void
    {
        $error = $validator->validateValue($value);
        Assert::assertNotNull($error);
        Assert::assertSame(ValidationErrorKind::UnsupportedValue, $error->kind);
        Assert::assertSame([], $error->failures);
        Assert::assertSame('The value is not JSON: ' . $reason, $error->getMessage());
    }
}
