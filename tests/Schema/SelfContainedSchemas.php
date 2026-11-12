<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use JsonException;
use stdClass;

use function in_array;
use function is_array;
use function json_decode;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/** @internal */
final class SelfContainedSchemas
{
    /** Keywords that name a schema or point to one, so that moving the schema can change their meaning. */
    private const array IDENTIFYING_KEYWORDS = [
        '$ref',
        '$dynamicRef',
        '$recursiveRef',
        '$id',
        'id',
        '$anchor',
        '$dynamicAnchor',
        '$recursiveAnchor',
        '$vocabulary',
    ];

    /**
     * Returns whether a schema document means the same inside another one: it neither identifies nor references a
     * schema, and it uses no custom meta-schema.
     *
     * @throws JsonException
     */
    public static function is(string $schemaJson): bool
    {
        $customMetaSchema = str_contains($schemaJson, 'localhost');
        return !$customMetaSchema && !self::identifies(json_decode($schemaJson, flags: JSON_THROW_ON_ERROR));
    }

    private static function identifies(mixed $json): bool
    {
        if (!$json instanceof stdClass && !is_array($json)) {
            return false;
        }
        /** @var mixed $member */
        foreach ((array) $json as $name => $member) {
            $identifies = in_array($name, self::IDENTIFYING_KEYWORDS, strict: true) || self::identifies($member);
            if ($identifies) {
                return true;
            }
        }
        return false;
    }
}
