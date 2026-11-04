<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Internal\Schema\Draft;
use Eventjet\Json\Internal\Schema\Uri;
use Eventjet\Json\Json;
use JsonException;
use stdClass;

use function array_key_exists;
use function is_bool;
use function is_string;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Decodes JSON Schema documents into the class of the draft that their `$schema` names.
 *
 * @api
 */
final class JsonSchema
{
    /** @var array<string, true> The meta-schemas whose draft is being found, to stop loops of meta-schemas. */
    private static array $resolving = [];

    /**
     * Decodes a schema document. A document whose `$schema` names an official meta-schema becomes an object of that
     * draft's class, and `true` and `false` become Booleans.
     *
     * Only the names of the parameters after the default class are stable, not their order, so pass them by name.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $default The class for a document without
     *     `$schema`.
     * @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader
     *     Returns the custom meta-schema with the given URI, for a `$schema` that isn't official, or null if there
     *     is none. The draft of the meta-schema it returns is the draft of the document. To follow the meta-schema's
     *     own `$schema`, the loader can decode it with this method. While the loader runs, this class remembers the
     *     URI, so a chain of `$schema` that leads back to it returns an error instead of never ending.
     */
    public static function decode(
        string $json,
        string $default,
        Closure|null $loader = null,
    ): Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError {
        try {
            /** @var mixed $data */
            $data = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new DecodeError(
                DecodeErrorKind::InvalidJson,
                'Invalid JSON: ' . $exception->getMessage(),
                $exception,
            );
        }
        if (is_bool($data)) {
            return $data;
        }
        $class = $default;
        /** @var mixed $metaSchema */
        $metaSchema = $data instanceof stdClass ? ((array) $data)['$schema'] ?? null : null;
        if (is_string($metaSchema)) {
            $resolved = self::classOf($metaSchema, $loader);
            if ($resolved instanceof DecodeError) {
                return $resolved;
            }
            $class = $resolved;
        }
        return Json::decode($json, $class);
    }

    /**
     * @param (Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null))|null $loader
     * @return class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012>|DecodeError
     */
    private static function classOf(string $metaSchema, Closure|null $loader): string|DecodeError
    {
        $official = Draft::ofMetaSchema($metaSchema);
        if ($official !== null) {
            return $official->className();
        }
        if ($loader === null) {
            return new DecodeError(DecodeErrorKind::InvalidValue, sprintf(
                'The $schema %s is not an official meta-schema, and there is no loader to load it.',
                $metaSchema,
            ));
        }
        $key = Uri::withoutEmptyFragment($metaSchema);
        if (array_key_exists($key, self::$resolving)) {
            return new DecodeError(DecodeErrorKind::InvalidValue, sprintf(
                'The meta-schema %s is its own meta-schema through a loop of $schema.',
                $metaSchema,
            ));
        }
        self::$resolving[$key] = true;
        try {
            $document = $loader($metaSchema);
        } finally {
            unset(self::$resolving[$key]);
        }
        if ($document instanceof DecodeError) {
            return $document;
        }
        if ($document === null || is_bool($document)) {
            return new DecodeError(DecodeErrorKind::InvalidValue, sprintf(
                'The loader found no meta-schema for the $schema %s.',
                $metaSchema,
            ));
        }
        return $document::class;
    }
}
