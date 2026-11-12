<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\SchemaError;
use JsonException;
use ReflectionException;
use stdClass;
use UnexpectedValueException;

use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Turns a schema object into the plain tree that json_decode() gives for its document, which the compiler walks.
 *
 * @internal
 */
final class SchemaTree
{
    /** @throws SchemaError */
    public static function of(Draft4|Draft6|Draft7|Draft201909|Draft202012 $schema): stdClass
    {
        try {
            /** @var stdClass */
            return json_decode(
                json_encode($schema->jsonSerialize(), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException|DecodeError|ReflectionException|UnexpectedValueException $exception) {
            throw new SchemaError('Could not read the schema: ' . $exception->getMessage(), $exception);
        }
    }
}
